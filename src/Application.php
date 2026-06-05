<?php

declare(strict_types=1);

namespace App;

use Throwable;

final class Application {

    private const WEBHOOK_TIMEOUT_SETTING = 'webhook_timeout_ms';
    private const DEFAULT_WEBHOOK_TIMEOUT_MS = 10000;
    private const MIN_WEBHOOK_TIMEOUT_MS = 1000;
    private const MAX_WEBHOOK_TIMEOUT_MS = 60000;
    private const DEFAULT_LONG_POLLING_MAX_TIMEOUT_SECONDS = 3;
    private const MIN_LONG_POLLING_MAX_TIMEOUT_SECONDS = 0;
    private const MAX_LONG_POLLING_MAX_TIMEOUT_SECONDS = 30;

    private Database $database;
    private BotApiController $botApi;
    private BotApiPayloadFactory $botApiPayloads;
    private BotApiRequestParser $requestParser;
    private ChatController $chat;
    private BotAdminController $botAdmin;
    private ProfileAdminController $profileAdmin;
    private ImportExportController $importExport;
    private BotRepository $bots;
    private BotCommandRepository $botCommands;
    private ChatRepository $chats;
    private ProfileRepository $profiles;
    private MessageRepository $messages;
    private UpdateRepository $updates;
    private DeliveryAttemptRepository $deliveryAttempts;
    private SettingsRepository $settings;
    private UpdateGenerator $updateGenerator;
    private WebhookDeliveryService $webhookDelivery;
    private InspectorController $inspector;
    private HttpLogRepository $httpLogs;
    private HttpLogger $httpLogger;
    private MediaStorage $mediaStorage;
    private LongPollingService $longPolling;
    private View $view;
    private ?string $rawBody = null;

    public function __construct(
        private string $rootPath,
        private string $dataDir,
        private string $logDir,
    ) {
        $this->database = new Database($this->dataDir);
        $this->bots = new BotRepository($this->database->pdo());
        $this->botCommands = new BotCommandRepository($this->database->pdo());
        $this->chats = new ChatRepository($this->database->pdo());
        $this->profiles = new ProfileRepository($this->database->pdo());
        $this->messages = new MessageRepository($this->database->pdo());
        $this->updates = new UpdateRepository($this->database->pdo());
        $this->deliveryAttempts = new DeliveryAttemptRepository($this->database->pdo());
        $this->settings = new SettingsRepository($this->database->pdo());
        $this->updateGenerator = new UpdateGenerator();
        $this->requestParser = new BotApiRequestParser();
        $this->botApiPayloads = new BotApiPayloadFactory();
        $this->mediaStorage = new MediaStorage(
            getenv('MEDIA_DIR') ?: $this->dataDir . '/media',
            $this->intParam(getenv('MEDIA_MAX_BYTES') ?: 10485760, 10485760),
        );
        $this->longPolling = new LongPollingService($this->updates);
        $this->webhookDelivery = new WebhookDeliveryService($this->deliveryAttempts, $this->updates);
        $this->botApi = new BotApiController(
            $this->bots,
            $this->botCommands,
            $this->profiles,
            $this->messages,
            $this->updates,
            $this->deliveryAttempts,
            $this->mediaStorage,
            $this->botApiPayloads,
            $this->longPolling,
            $this->longPollingMaxTimeoutSeconds(),
        );
        $this->httpLogs = new HttpLogRepository($this->logDir);
        $this->httpLogger = new HttpLogger($this->logDir);
        $this->view = new View($this->rootPath . '/templates');
        $this->inspector = new InspectorController(
            $this->bots,
            $this->profiles,
            $this->updates,
            $this->deliveryAttempts,
            $this->httpLogs,
            $this->webhookDelivery,
            $this->view,
            fn(): int => $this->webhookTimeoutSeconds(),
        );
        $this->chat = new ChatController(
            $this->database,
            $this->bots,
            $this->botCommands,
            $this->profiles,
            $this->messages,
            $this->updates,
            $this->deliveryAttempts,
            $this->updateGenerator,
            $this->webhookDelivery,
            $this->mediaStorage,
            $this->view,
            fn(): int => $this->webhookTimeoutSeconds(),
        );
        $this->botAdmin = new BotAdminController(
            $this->bots,
            $this->profiles,
            $this->view,
        );
        $this->profileAdmin = new ProfileAdminController(
            $this->profiles,
            $this->bots,
            $this->view,
        );
        $this->importExport = new ImportExportController(
            $this->bots,
            $this->profiles,
            $this->chats,
            $this->botCommands,
            $this->view,
            fn(): string => $this->rawBody(),
        );
    }

    /**
     * Возвращает сырое тело запроса (используется для Bot API методов с JSON body).
     */
    public function rawBody(): string {
        if ($this->rawBody === null) {
            $this->rawBody = (string) file_get_contents('php://input');
        }

        return $this->rawBody;
    }

    /**
     * Парсит тело запроса в $_POST вручную (т.к. enable_post_data_reading = Off).
     */
    private function parseInput(): void {
        $parsed = $this->requestParser->parse(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $this->rawBody(),
            $_SERVER['CONTENT_TYPE'] ?? '',
        );

        if ($parsed !== null) {
            $_POST = $parsed;
        }
    }

    public function handle(string $method, string $path): void {
        $startedAt = microtime(true);
        $logContext = $this->httpLogger->requestContext($_SERVER, $this->rawBody());
        $errorContext = null;

        try {
            $this->parseInput();
            $this->boot();
            $this->route($method, rtrim($path, '/') ?: '/');
        } catch (Throwable $exception) {
            $errorContext = $this->httpLogger->errorContext($exception);
            Response::json([
                'ok' => false,
                'error' => 'Внутренняя ошибка приложения',
                'details' => $exception->getMessage(),
            ], 500);
        } finally {
            try {
                $this->httpLogger->log(array_replace_recursive($logContext, [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'response' => [
                        'status' => http_response_code(),
                        'headers' => headers_list(),
                        'body' => $this->currentOutput(),
                    ],
                    'error' => $errorContext,
                ]));
            } catch (Throwable $loggerException) {
                error_log('HTTP logger failed: ' . $loggerException->getMessage());
            }
        }
    }

    private function currentOutput(): string {
        if (ob_get_level() <= 0) {
            return '';
        }

        return (string) ob_get_contents();
    }

    private function route(string $method, string $path): void {
        if ($this->chat->handle($method, $path)) {
            return;
        }

        // --- Панель и health ---

        if ($method === 'GET' && in_array($path, ['/', '/index.php'], true)) {
            $this->dashboard();
            return;
        }

        if ($method === 'POST' && $path === '/settings/webhook-timeout') {
            $this->updateWebhookTimeout();
            return;
        }

        if ($method === 'GET' && $path === '/health') {
            $this->health();
            return;
        }

        if ($method === 'GET' && preg_match('#^/file/bot([^/]+)/(.+)$#', $path, $matches) === 1) {
            $this->downloadMedia($matches[1], rawurldecode($matches[2]));
            return;
        }

        if ($this->inspector->handle($method, $path)) {
            return;
        }

        if ($this->importExport->handle($method, $path)) {
            return;
        }

        if ($this->botAdmin->handle($method, $path)) {
            return;
        }

        if ($this->profileAdmin->handle($method, $path)) {
            return;
        }

        if ($this->botApi->handle($method, $path)) {
            return;
        }

        Response::json([
            'ok' => false,
            'error' => 'Маршрут не найден',
        ], 404);
    }

    // ----------------------------------------------------------------
    // Инфраструктура
    // ----------------------------------------------------------------

    /**
     * Рендерит шаблон, автоматически добавляя пользователей и ботов для выбора контекста.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->render($template, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderPartial(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->renderPartial($template, $data);
    }

    private function boot(): void {
        $runner = new MigrationRunner(
            pdo: $this->database->pdo(),
            migrationsPath: $this->rootPath . '/migrations',
        );

        $runner->run();
    }

    private function dashboard(): void {
        $this->render('dashboard', [
            'title' => 'Панель',
            'bots' => $this->bots->all(),
            'users' => $this->profiles->all(),
            'databasePath' => $this->database->path(),
            'webhookTimeoutMs' => $this->webhookTimeoutMs(),
            'webhookTimeoutDefaultMs' => $this->webhookTimeoutDefaultMs(),
            'webhookTimeoutMinMs' => self::MIN_WEBHOOK_TIMEOUT_MS,
            'webhookTimeoutMaxMs' => self::MAX_WEBHOOK_TIMEOUT_MS,
        ]);
    }

    private function updateWebhookTimeout(): void {
        $rawValue = $_POST['webhook_timeout_ms'] ?? null;
        $timeoutMs = $this->intParam($rawValue, 0);

        if ($timeoutMs < self::MIN_WEBHOOK_TIMEOUT_MS || $timeoutMs > self::MAX_WEBHOOK_TIMEOUT_MS) {
            Response::json([
                'ok' => false,
                'error' => 'Webhook timeout должен быть целым числом от '
                    . self::MIN_WEBHOOK_TIMEOUT_MS . ' до '
                    . self::MAX_WEBHOOK_TIMEOUT_MS . ' мс',
            ], 400);
            return;
        }

        $this->settings->set(self::WEBHOOK_TIMEOUT_SETTING, (string) $timeoutMs);
        Response::redirect('/');
    }

    private function downloadMedia(string $token, string $filePath): void {
        if ($this->bots->findByToken($token) === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $resolvedPath = $this->mediaStorage->resolveDownloadPath($filePath);
        if ($resolvedPath === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'File not found',
            ], 404);
            return;
        }

        if (ob_get_level() > 0) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $this->mediaStorage->contentType($resolvedPath));
            header('Content-Length: ' . (string) filesize($resolvedPath));
            header('Content-Disposition: inline; filename="' . basename($resolvedPath) . '"');
        }

        readfile($resolvedPath);
    }

    private function intParam(mixed $value, int $default): int {
        if (is_int($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return $default;
        }

        return (int) $value;
    }

    private function webhookTimeoutSeconds(): int {
        $timeoutMs = $this->webhookTimeoutMs();

        return (int) ceil($timeoutMs / 1000);
    }

    private function webhookTimeoutMs(): int {
        $stored = $this->settings->get(self::WEBHOOK_TIMEOUT_SETTING);
        $timeoutMs = $stored === null
            ? $this->webhookTimeoutDefaultMs()
            : $this->intParam($stored, self::DEFAULT_WEBHOOK_TIMEOUT_MS);

        return max(self::MIN_WEBHOOK_TIMEOUT_MS, min(self::MAX_WEBHOOK_TIMEOUT_MS, $timeoutMs));
    }

    private function webhookTimeoutDefaultMs(): int {
        $timeoutMs = $this->intParam(getenv('WEBHOOK_TIMEOUT_MS') ?: self::DEFAULT_WEBHOOK_TIMEOUT_MS, self::DEFAULT_WEBHOOK_TIMEOUT_MS);

        return max(self::MIN_WEBHOOK_TIMEOUT_MS, min(self::MAX_WEBHOOK_TIMEOUT_MS, $timeoutMs));
    }

    private function longPollingMaxTimeoutSeconds(): int {
        $timeout = $this->intParam(
            getenv('LONG_POLLING_MAX_TIMEOUT_SECONDS') ?: self::DEFAULT_LONG_POLLING_MAX_TIMEOUT_SECONDS,
            self::DEFAULT_LONG_POLLING_MAX_TIMEOUT_SECONDS,
        );

        return max(
            self::MIN_LONG_POLLING_MAX_TIMEOUT_SECONDS,
            min(self::MAX_LONG_POLLING_MAX_TIMEOUT_SECONDS, $timeout),
        );
    }

    private function health(): void {
        Response::json([
            'ok' => true,
            'service' => 'telegram-emulator',
            'storage' => [
                'driver' => 'sqlite',
                'path' => $this->database->path(),
            ],
        ]);
    }
}

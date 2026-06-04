<?php

declare(strict_types=1);

namespace App;

use Throwable;

final class Application {

    private const WEBHOOK_TIMEOUT_SETTING = 'webhook_timeout_ms';
    private const DEFAULT_WEBHOOK_TIMEOUT_MS = 10000;
    private const MIN_WEBHOOK_TIMEOUT_MS = 1000;
    private const MAX_WEBHOOK_TIMEOUT_MS = 60000;

    private Database $database;
    private BotApiController $botApi;
    private BotApiRequestParser $requestParser;
    private ChatController $chat;
    private BotRepository $bots;
    private BotCommandRepository $botCommands;
    private ProfileRepository $profiles;
    private MessageRepository $messages;
    private UpdateRepository $updates;
    private DeliveryAttemptRepository $deliveryAttempts;
    private SettingsRepository $settings;
    private UpdateGenerator $updateGenerator;
    private WebhookDeliveryService $webhookDelivery;
    private HttpLogRepository $httpLogs;
    private HttpLogger $httpLogger;
    private MediaStorage $mediaStorage;
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
        $this->profiles = new ProfileRepository($this->database->pdo());
        $this->messages = new MessageRepository($this->database->pdo());
        $this->updates = new UpdateRepository($this->database->pdo());
        $this->deliveryAttempts = new DeliveryAttemptRepository($this->database->pdo());
        $this->settings = new SettingsRepository($this->database->pdo());
        $this->updateGenerator = new UpdateGenerator();
        $this->requestParser = new BotApiRequestParser();
        $this->mediaStorage = new MediaStorage(
            getenv('MEDIA_DIR') ?: $this->dataDir . '/media',
            $this->intParam(getenv('MEDIA_MAX_BYTES') ?: 10485760, 10485760),
        );
        $this->webhookDelivery = new WebhookDeliveryService($this->deliveryAttempts, $this->updates);
        $this->botApi = new BotApiController(
            $this->bots,
            $this->botCommands,
            $this->profiles,
            $this->messages,
            $this->updates,
            $this->deliveryAttempts,
            $this->mediaStorage,
        );
        $this->httpLogs = new HttpLogRepository($this->logDir);
        $this->httpLogger = new HttpLogger($this->logDir);
        $this->view = new View($this->rootPath . '/templates');
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

        if ($method === 'POST' && preg_match('#^/updates/(\d+)/resend$#', $path, $matches) === 1) {
            $this->resendWebhookUpdate((int) $matches[1]);
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

        if ($method === 'GET' && $path === '/delivery-attempts') {
            $this->deliveryAttemptsIndex();
            return;
        }

        if ($method === 'GET' && $path === '/updates') {
            $this->updatesIndex();
            return;
        }

        if ($method === 'POST' && $path === '/updates/clear') {
            $this->updatesClear();
            return;
        }

        if ($method === 'GET' && $path === '/request-inspector') {
            $this->requestInspector();
            return;
        }

        if ($method === 'GET' && $path === '/import-export') {
            $this->importExportIndex();
            return;
        }

        if ($method === 'GET' && $path === '/export/bots') {
            $this->exportBots();
            return;
        }

        if ($method === 'GET' && $path === '/export/profiles') {
            $this->exportProfiles();
            return;
        }

        if ($method === 'POST' && $path === '/import/bots') {
            $this->importBots();
            return;
        }

        if ($method === 'POST' && $path === '/import/profiles') {
            $this->importProfiles();
            return;
        }

        // --- Боты ---

        if ($method === 'GET' && $path === '/bots') {
            $this->botsIndex();
            return;
        }

        if ($method === 'GET' && $path === '/bots/new') {
            $this->botForm();
            return;
        }

        if ($method === 'POST' && $path === '/bots') {
            $this->botCreate();
            return;
        }

        if ($method === 'GET' && preg_match('#^/bots/(\d+)/edit$#', $path, $matches) === 1) {
            $this->botForm((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bots/(\d+)$#', $path, $matches) === 1) {
            $this->botUpdate((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bots/(\d+)/delete$#', $path, $matches) === 1) {
            $this->bots->delete((int) $matches[1]);
            Response::redirect('/bots');
            return;
        }

        // --- Пользователи ---

        if ($method === 'GET' && $path === '/profiles') {
            $this->profilesIndex();
            return;
        }

        if ($method === 'GET' && $path === '/profiles/new') {
            $this->profileForm();
            return;
        }

        if ($method === 'POST' && $path === '/profiles') {
            $this->profileCreate();
            return;
        }

        if ($method === 'GET' && preg_match('#^/profiles/(\d+)/edit$#', $path, $matches) === 1) {
            $this->profileForm((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/(\d+)$#', $path, $matches) === 1) {
            $this->profileUpdate((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/(\d+)/delete$#', $path, $matches) === 1) {
            $this->profiles->delete((int) $matches[1]);
            Response::redirect('/profiles');
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

    private function resendWebhookUpdate(int $updateRowId): void {
        $update = $this->updates->find($updateRowId);

        if ($update === null) {
            Response::json([
                'ok' => false,
                'error' => 'Update не найден',
            ], 404);
            return;
        }

        if ((string) ($update['queue_state'] ?? '') !== 'failed') {
            Response::json([
                'ok' => false,
                'error' => 'Повторная отправка доступна только для failed updates',
            ], 400);
            return;
        }

        $bot = $this->bots->find((int) $update['bot_id']);
        if ($bot === null || trim((string) ($bot['webhook_url'] ?? '')) === '') {
            Response::json([
                'ok' => false,
                'error' => 'У бота не настроен webhook URL',
            ], 400);
            return;
        }

        $this->webhookDelivery->deliver([
            'id' => (int) $update['id'],
            'update_id' => (int) $update['update_id'],
            'payload' => (string) $update['payload'],
        ], $bot, $this->webhookTimeoutSeconds());

        Response::redirect('/chat?profile_id=' . (int) $update['profile_id'] . '&bot_id=' . (int) $bot['id']);
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

    private function botsIndex(): void {
        $this->render('bots/index', [
            'title' => 'Боты',
            'bots' => $this->bots->all(),
        ]);
    }

    private function botForm(?int $id = null): void {
        $bot = $id === null ? null : $this->bots->find($id);

        if ($id !== null && $bot === null) {
            Response::json(['ok' => false, 'error' => 'Бот не найден'], 404);
            return;
        }

        $this->render('bots/form', [
            'title' => $bot === null ? 'Новый бот' : 'Редактирование бота',
            'bot' => $bot,
            'generatedCredentials' => $bot === null ? $this->bots->generateCredentials() : null,
            'errors' => [],
        ]);
    }

    private function botCreate(): void {
        $errors = $this->validateBotForm($_POST);

        if ($errors !== []) {
            http_response_code(422);
            $this->render('bots/form', [
                'title' => 'Новый бот',
                'bot' => $_POST,
                'generatedCredentials' => $this->generatedCredentialsFromPost($_POST) ?? $this->bots->generateCredentials(),
                'errors' => $errors,
            ]);
            return;
        }

        $this->bots->create($_POST);
        Response::redirect('/bots');
    }

    private function botUpdate(int $id): void {
        $bot = $this->bots->find($id);

        if ($bot === null) {
            Response::json(['ok' => false, 'error' => 'Бот не найден'], 404);
            return;
        }

        $errors = $this->validateBotForm($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->render('bots/form', [
                'title' => 'Редактирование бота',
                'bot' => array_replace($bot, $_POST, ['id' => $id]),
                'generatedCredentials' => null,
                'errors' => $errors,
            ]);
            return;
        }

        $this->bots->update($id, $_POST);
        Response::redirect('/bots');
    }

    private function profilesIndex(): void {
        $this->render('profiles/index', [
            'title' => 'Пользователи',
            'users' => $this->profiles->all(),
        ]);
    }

    private function deliveryAttemptsIndex(): void {
        $botId = $this->intParam($_GET['bot_id'] ?? 0, 0);
        $updateId = $this->intParam($_GET['update_id'] ?? 0, 0);

        $this->render('delivery-attempts/index', [
            'title' => 'Webhook delivery attempts',
            'attempts' => $this->deliveryAttempts->allWithContext(
                $botId > 0 ? $botId : null,
                $updateId > 0 ? $updateId : null,
            ),
            'selectedBotId' => $botId,
            'selectedUpdateId' => $updateId,
        ]);
    }

    private function updatesIndex(): void {
        $botId = $this->intParam($_GET['bot_id'] ?? 0, 0);
        $profileId = $this->intParam($_GET['profile_id'] ?? 0, 0);
        $updateId = $this->intParam($_GET['update_id'] ?? 0, 0);
        $queueState = (string) ($_GET['queue_state'] ?? '');
        $queueState = in_array($queueState, ['pending', 'delivered', 'confirmed', 'failed'], true)
            ? $queueState
            : '';

        $this->render('updates/index', [
            'title' => 'Updates',
            'updates' => $this->updates->allWithContext(
                $botId > 0 ? $botId : null,
                $profileId > 0 ? $profileId : null,
                $updateId > 0 ? $updateId : null,
                $queueState === '' ? null : $queueState,
            ),
            'selectedBotId' => $botId,
            'selectedProfileId' => $profileId,
            'selectedUpdateId' => $updateId,
            'selectedQueueState' => $queueState,
        ]);
    }

    private function updatesClear(): void {
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $bot = $this->bots->find($botId);

        if ($bot === null || (string) ($_POST['confirm_clear'] ?? '') !== '1') {
            Response::json([
                'ok' => false,
                'error' => 'Для очистки updates нужно выбрать бота и подтвердить действие',
            ], 400);
            return;
        }

        $this->updates->deletePendingAndConfirmedByBot((int) $bot['id']);
        Response::redirect('/updates?bot_id=' . (int) $bot['id']);
    }

    private function requestInspector(): void {
        $tokenFilter = trim((string) ($_GET['token'] ?? ''));
        $methodFilter = trim((string) ($_GET['method'] ?? ''));

        $this->render('request-inspector/index', [
            'title' => 'Request inspector',
            'botApiEvents' => $this->httpLogs->botApiEvents(
                $tokenFilter === '' ? null : $tokenFilter,
                $methodFilter === '' ? null : $methodFilter,
                100,
            ),
            'webhookAttempts' => array_map(
                fn(array $attempt): array => $this->maskedWebhookAttempt($attempt),
                $this->deliveryAttempts->allWithContext(null, null, 50),
            ),
            'selectedMethod' => $methodFilter,
            'hasTokenFilter' => $tokenFilter !== '',
        ]);
    }

    private function importExportIndex(): void {
        $this->render('import-export/index', [
            'title' => 'Import/export',
        ]);
    }

    private function exportBots(): void {
        Response::json([
            'ok' => true,
            'version' => 1,
            'exported_at' => date('c'),
            'bots' => array_map(
                fn(array $bot): array => $this->exportBotPayload($bot),
                $this->bots->all(),
            ),
        ]);
    }

    private function exportProfiles(): void {
        Response::json([
            'ok' => true,
            'version' => 1,
            'exported_at' => date('c'),
            'profiles' => array_map(
                fn(array $profile): array => $this->exportProfilePayload($profile),
                $this->profiles->all(),
            ),
        ]);
    }

    private function importBots(): void {
        $payload = $this->importPayload('bots');
        if (!is_array($payload)) {
            return;
        }

        $botsToCreate = [];
        $seenTokens = [];
        foreach ($payload as $index => $bot) {
            if (!is_array($bot)) {
                Response::json(['ok' => false, 'error' => 'bots[' . $index . '] должен быть объектом'], 400);
                return;
            }

            $bot = $this->normalizedImportEnabled($bot);
            $errors = $this->validateBotForm($bot);
            if ($errors !== []) {
                Response::json(['ok' => false, 'error' => 'Некорректный bot payload', 'details' => $errors], 400);
                return;
            }

            $token = trim((string) ($bot['token'] ?? ''));
            if ($token === '' || isset($seenTokens[$token]) || $this->bots->hasToken($token)) {
                Response::json(['ok' => false, 'error' => 'Конфликт token при импорте бота'], 409);
                return;
            }

            $seenTokens[$token] = true;
            $botsToCreate[] = $bot;
        }

        foreach ($botsToCreate as $bot) {
            $this->bots->create($bot);
        }

        Response::json(['ok' => true, 'created' => count($botsToCreate)]);
    }

    private function importProfiles(): void {
        $payload = $this->importPayload('profiles');
        if (!is_array($payload)) {
            return;
        }

        $profilesToCreate = [];
        $seenUserIds = [];
        $seenChatIds = [];
        foreach ($payload as $index => $profile) {
            if (!is_array($profile)) {
                Response::json(['ok' => false, 'error' => 'profiles[' . $index . '] должен быть объектом'], 400);
                return;
            }

            $profile = $this->normalizedImportEnabled($profile);
            $errors = $this->validateProfileForm($profile);
            if ($errors !== []) {
                Response::json(['ok' => false, 'error' => 'Некорректный profile payload', 'details' => $errors], 400);
                return;
            }

            $userId = (int) $profile['user_id'];
            $chatId = (int) $profile['chat_id'];
            if (isset($seenUserIds[$userId]) || $this->profiles->hasUserId($userId)) {
                Response::json(['ok' => false, 'error' => 'Конфликт user_id при импорте пользователя'], 409);
                return;
            }

            $chatType = (string) ($profile['chat_type'] ?? 'private');
            if (
                $this->hasConflictingImportedChatId($seenChatIds, $chatId, $chatType)
                || $this->profiles->hasConflictingChatId($chatId, $chatType)
            ) {
                Response::json(['ok' => false, 'error' => 'Конфликт chat_id при импорте пользователя'], 409);
                return;
            }

            $seenUserIds[$userId] = true;
            $seenChatIds[$chatId][] = $chatType;
            $profilesToCreate[] = $profile;
        }

        foreach ($profilesToCreate as $profile) {
            $this->profiles->create($profile);
        }

        Response::json(['ok' => true, 'created' => count($profilesToCreate)]);
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function importPayload(string $rootKey): ?array {
        $raw = trim((string) ($_POST['payload'] ?? ''));
        if ($raw === '') {
            $raw = $this->rawBody();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Response::json(['ok' => false, 'error' => 'Ожидался JSON payload'], 400);
            return null;
        }

        $items = isset($decoded[$rootKey]) && is_array($decoded[$rootKey])
            ? $decoded[$rootKey]
            : $decoded;

        if (!array_is_list($items)) {
            Response::json(['ok' => false, 'error' => 'Ожидался массив ' . $rootKey], 400);
            return null;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizedImportEnabled(array $data): array {
        $enabled = $this->isTruthyBotApiParam($data['enabled'] ?? true);
        if ($enabled) {
            $data['enabled'] = '1';
        } else {
            unset($data['enabled']);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $bot
     * @return array<string, mixed>
     */
    private function exportBotPayload(array $bot): array {
        return [
            'token' => $bot['token'],
            'bot_id' => (int) ($bot['bot_id'] ?? 0),
            'username' => $bot['username'],
            'display_name' => $bot['display_name'],
            'delivery_mode' => $bot['delivery_mode'],
            'webhook_url' => $bot['webhook_url'],
            'webhook_secret_token' => $bot['webhook_secret_token'],
            'enabled' => ((int) $bot['enabled']) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function exportProfilePayload(array $profile): array {
        return [
            'user_id' => (int) $profile['user_id'],
            'username' => $profile['username'],
            'first_name' => $profile['first_name'],
            'last_name' => $profile['last_name'],
            'chat_id' => (int) $profile['chat_id'],
            'chat_type' => $profile['chat_type'],
            'language_code' => $profile['language_code'],
            'enabled' => ((int) $profile['enabled']) === 1,
        ];
    }

    /**
     * @param array<string, mixed> $attempt
     * @return array<string, mixed>
     */
    private function maskedWebhookAttempt(array $attempt): array {
        foreach (['webhook_url', 'request_headers', 'request_body', 'response_headers', 'response_body', 'error'] as $field) {
            if (array_key_exists($field, $attempt) && $attempt[$field] !== null) {
                $attempt[$field] = $this->maskSecrets((string) $attempt[$field]);
            }
        }

        return $attempt;
    }

    private function maskSecrets(string $value): string {
        $masked = preg_replace_callback(
            '/(\d{5,10}):[a-zA-Z0-9_.+-]{15,}/',
            fn(array $matches): string => $matches[1] . ':***',
            $value,
        ) ?? $value;

        $masked = preg_replace('/(X-Telegram-Bot-Api-Secret-Token:\s*)[^\r\n",]+/i', '$1***', $masked) ?? $masked;

        return preg_replace('/("(?:webhook_)?secret_token"\s*:\s*")[^"]+(")/i', '$1***$2', $masked) ?? $masked;
    }

    private function profileForm(?int $id = null): void {
        $profile = $id === null ? null : $this->profiles->find($id);

        if ($id !== null && $profile === null) {
            Response::json(['ok' => false, 'error' => 'Пользователь не найден'], 404);
            return;
        }

        $this->render('profiles/form', [
            'title' => $profile === null ? 'Новый пользователь' : 'Редактирование пользователя',
            'profile' => $profile,
            'errors' => [],
        ]);
    }

    private function profileCreate(): void {
        $errors = $this->validateProfileForm($_POST);

        if ($errors !== []) {
            http_response_code(422);
            $this->render('profiles/form', [
                'title' => 'Новый пользователь',
                'profile' => $_POST,
                'errors' => $errors,
            ]);
            return;
        }

        $this->profiles->create($_POST);
        Response::redirect('/profiles');
    }

    private function profileUpdate(int $id): void {
        $profile = $this->profiles->find($id);

        if ($profile === null) {
            Response::json(['ok' => false, 'error' => 'Пользователь не найден'], 404);
            return;
        }

        $errors = $this->validateProfileForm($_POST);
        if ($errors !== []) {
            http_response_code(422);
            $this->render('profiles/form', [
                'title' => 'Редактирование пользователя',
                'profile' => array_replace($profile, $_POST, ['id' => $id]),
                'errors' => $errors,
            ]);
            return;
        }

        $this->profiles->update($id, $_POST);
        Response::redirect('/profiles');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateBotForm(array $data): array {
        $errors = [];
        $displayName = trim((string) ($data['display_name'] ?? ''));
        $username = ltrim(trim((string) ($data['username'] ?? '')), '@');
        $token = trim((string) ($data['token'] ?? ''));
        $botId = trim((string) ($data['bot_id'] ?? ''));
        $deliveryMode = (string) ($data['delivery_mode'] ?? '');
        $webhookUrl = trim((string) ($data['webhook_url'] ?? ''));

        if ($displayName === '') {
            $errors['display_name'] = 'Укажите название бота.';
        }

        if ($username === '') {
            $errors['username'] = 'Укажите username бота.';
        } elseif (preg_match('/^[A-Za-z0-9_]{1,32}$/', $username) !== 1) {
            $errors['username'] = 'Username может содержать латинские буквы, цифры и underscore, до 32 символов.';
        }

        if ($token !== '' && preg_match('/^\d{5,10}:[a-zA-Z0-9_.+-]{15,}$/', $token) !== 1) {
            $errors['token'] = 'Token должен выглядеть как 123456:local-dev-token.';
        }

        if ($botId !== '' && preg_match('/^\d{5,10}$/', $botId) !== 1) {
            $errors['bot_id'] = 'Bot ID должен быть числом от 5 до 10 цифр.';
        }

        if (!in_array($deliveryMode, ['webhook', 'long_polling'], true)) {
            $errors['delivery_mode'] = 'Выберите допустимый режим доставки.';
        }

        if ($webhookUrl !== '' && !$this->isValidWebhookUrl($webhookUrl)) {
            $errors['webhook_url'] = 'Webhook URL должен быть корректным http или https URL.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateProfileForm(array $data): array {
        $errors = [];
        $userId = trim((string) ($data['user_id'] ?? ''));
        $username = ltrim(trim((string) ($data['username'] ?? '')), '@');
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $chatId = trim((string) ($data['chat_id'] ?? ''));
        $chatType = (string) ($data['chat_type'] ?? '');
        $languageCode = trim((string) ($data['language_code'] ?? ''));

        if ($userId === '' || preg_match('/^-?\d+$/', $userId) !== 1 || (int) $userId === 0) {
            $errors['user_id'] = 'User ID должен быть ненулевым целым числом.';
        }

        if ($username === '') {
            $errors['username'] = 'Укажите username пользователя.';
        } elseif (preg_match('/^[A-Za-z0-9_]{1,32}$/', $username) !== 1) {
            $errors['username'] = 'Username может содержать латинские буквы, цифры и underscore, до 32 символов.';
        }

        if ($firstName === '') {
            $errors['first_name'] = 'Укажите имя пользователя.';
        }

        if ($chatId === '' || preg_match('/^-?\d+$/', $chatId) !== 1 || (int) $chatId === 0) {
            $errors['chat_id'] = 'Chat ID должен быть ненулевым целым числом.';
        }

        if (!in_array($chatType, ['private', 'group', 'supergroup', 'channel'], true)) {
            $errors['chat_type'] = 'Выберите допустимый тип чата.';
        }

        if ($languageCode !== '' && preg_match('/^[a-z]{2,8}(?:-[A-Z]{2})?$/', $languageCode) !== 1) {
            $errors['language_code'] = 'Язык должен быть кодом вроде ru или en-US.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{bot_id: int, token: string}|null
     */
    private function generatedCredentialsFromPost(array $data): ?array {
        $token = trim((string) ($data['generated_token'] ?? ''));
        if (preg_match('/^(\d{5,10}):[a-zA-Z0-9_.+-]{15,}$/', $token, $matches) !== 1) {
            return null;
        }

        return [
            'bot_id' => (int) $matches[1],
            'token' => $token,
        ];
    }

    private function isValidWebhookUrl(string $url): bool {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        return $host !== '' && in_array($scheme, ['http', 'https'], true);
    }

    private function isTruthyBotApiParam(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
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

    private function isGroupChatType(string $chatType): bool {
        return in_array($chatType, ['group', 'supergroup'], true);
    }

    /**
     * @param array<int, list<string>> $seenChatIds
     */
    private function hasConflictingImportedChatId(array $seenChatIds, int $chatId, string $chatType): bool {
        if (!isset($seenChatIds[$chatId])) {
            return false;
        }

        if (!$this->isGroupChatType($chatType)) {
            return true;
        }

        foreach ($seenChatIds[$chatId] as $seenChatType) {
            if (!$this->isGroupChatType($seenChatType)) {
                return true;
            }
        }

        return false;
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

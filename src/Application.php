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

        if ($method === 'GET' && $path === '/export/fixture-pack') {
            $this->exportFixturePack();
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

        if ($method === 'POST' && $path === '/import/fixture-pack') {
            $this->importFixturePack();
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

    private function exportFixturePack(): void {
        Response::json([
            'ok' => true,
            'version' => 2,
            'kind' => 'telegram-emulator-fixture-pack',
            'exported_at' => date('c'),
            'bots' => array_map(
                fn(array $bot): array => $this->exportBotPayload($bot),
                $this->bots->all(),
            ),
            'profiles' => array_map(
                fn(array $profile): array => $this->exportProfilePayload($profile),
                $this->profiles->all(),
            ),
            'chats' => array_map(
                fn(array $chat): array => $this->exportChatPayload($chat),
                $this->chats->all(),
            ),
            'bot_commands' => $this->botCommands->allWithBotTokens(),
            'media_manifest' => [
                'included' => false,
                'note' => 'Binary media files are not embedded in JSON fixture packs.',
            ],
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

    private function importFixturePack(): void {
        $payload = $this->fixturePackPayload();
        if ($payload === null) {
            return;
        }

        $bots = $payload['bots'] ?? [];
        $profiles = $payload['profiles'] ?? [];
        $botCommands = $payload['bot_commands'] ?? [];
        $chats = $payload['chats'] ?? [];

        if (!is_array($bots) || !array_is_list($bots)) {
            Response::json(['ok' => false, 'error' => 'Ожидался массив bots'], 400);
            return;
        }

        if (!is_array($profiles) || !array_is_list($profiles)) {
            Response::json(['ok' => false, 'error' => 'Ожидался массив profiles'], 400);
            return;
        }

        if (!is_array($botCommands) || !array_is_list($botCommands)) {
            Response::json(['ok' => false, 'error' => 'Ожидался массив bot_commands'], 400);
            return;
        }

        if (!is_array($chats) || !array_is_list($chats)) {
            Response::json(['ok' => false, 'error' => 'Ожидался массив chats'], 400);
            return;
        }

        $botsToCreate = $this->validatedImportBots($bots);
        if ($botsToCreate === null) {
            return;
        }

        $profilesToCreate = $this->validatedImportProfiles($profiles);
        if ($profilesToCreate === null) {
            return;
        }

        $commandsToImport = $this->validatedImportBotCommands($botCommands, $botsToCreate);
        if ($commandsToImport === null) {
            return;
        }

        if (!$this->validFixtureChats($chats, $profilesToCreate)) {
            return;
        }

        foreach ($botsToCreate as $bot) {
            $this->bots->create($bot);
        }

        foreach ($profilesToCreate as $profile) {
            $this->profiles->create($profile);
        }

        foreach ($chats as $chat) {
            $this->chats->upsertMetadata(
                (int) $chat['chat_id'],
                (string) $chat['type'],
                $this->fixtureChatTitle($chat),
            );
        }

        foreach ($commandsToImport as $commandGroup) {
            $bot = $this->bots->findByToken($commandGroup['bot_token']);
            if ($bot !== null) {
                $this->botCommands->replaceForBot(
                    (int) $bot['id'],
                    $commandGroup['commands'],
                    $commandGroup['scope'],
                    $commandGroup['language_code'],
                );
            }
        }

        Response::json([
            'ok' => true,
            'created' => [
                'bots' => count($botsToCreate),
                'profiles' => count($profilesToCreate),
                'bot_commands' => count($commandsToImport),
                'chats' => count($chats),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fixturePackPayload(): ?array {
        $raw = trim((string) ($_POST['payload'] ?? ''));
        if ($raw === '') {
            $raw = $this->rawBody();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            Response::json(['ok' => false, 'error' => 'Ожидался JSON fixture pack object'], 400);
            return null;
        }

        return $decoded;
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
     * @param list<mixed> $bots
     * @return list<array<string, mixed>>|null
     */
    private function validatedImportBots(array $bots): ?array {
        $botsToCreate = [];
        $seenTokens = [];
        foreach ($bots as $index => $bot) {
            if (!is_array($bot)) {
                Response::json(['ok' => false, 'error' => 'bots[' . $index . '] должен быть объектом'], 400);
                return null;
            }

            $bot = $this->normalizedImportEnabled($bot);
            $errors = $this->validateBotForm($bot);
            if ($errors !== []) {
                Response::json(['ok' => false, 'error' => 'Некорректный bot payload', 'details' => $errors], 400);
                return null;
            }

            $token = trim((string) ($bot['token'] ?? ''));
            if ($token === '' || isset($seenTokens[$token]) || $this->bots->hasToken($token)) {
                Response::json(['ok' => false, 'error' => 'Конфликт token при импорте бота'], 409);
                return null;
            }

            $seenTokens[$token] = true;
            $botsToCreate[] = $bot;
        }

        return $botsToCreate;
    }

    /**
     * @param list<mixed> $profiles
     * @return list<array<string, mixed>>|null
     */
    private function validatedImportProfiles(array $profiles): ?array {
        $profilesToCreate = [];
        $seenUserIds = [];
        $seenChatIds = [];
        foreach ($profiles as $index => $profile) {
            if (!is_array($profile)) {
                Response::json(['ok' => false, 'error' => 'profiles[' . $index . '] должен быть объектом'], 400);
                return null;
            }

            $profile = $this->normalizedImportEnabled($profile);
            $errors = $this->validateProfileForm($profile);
            if ($errors !== []) {
                Response::json(['ok' => false, 'error' => 'Некорректный profile payload', 'details' => $errors], 400);
                return null;
            }

            $userId = (int) $profile['user_id'];
            $chatId = (int) $profile['chat_id'];
            if (isset($seenUserIds[$userId]) || $this->profiles->hasUserId($userId)) {
                Response::json(['ok' => false, 'error' => 'Конфликт user_id при импорте пользователя'], 409);
                return null;
            }

            $chatType = (string) ($profile['chat_type'] ?? 'private');
            if (
                $this->hasConflictingImportedChatId($seenChatIds, $chatId, $chatType)
                || $this->profiles->hasConflictingChatId($chatId, $chatType)
            ) {
                Response::json(['ok' => false, 'error' => 'Конфликт chat_id при импорте пользователя'], 409);
                return null;
            }

            $seenUserIds[$userId] = true;
            $seenChatIds[$chatId][] = $chatType;
            $profilesToCreate[] = $profile;
        }

        return $profilesToCreate;
    }

    /**
     * @param list<mixed> $botCommands
     * @param list<array<string, mixed>> $botsToCreate
     * @return list<array{bot_token: string, scope: array<string, mixed>, language_code: string, commands: list<array{command: string, description: string}>}>|null
     */
    private function validatedImportBotCommands(array $botCommands, array $botsToCreate): ?array {
        $availableTokens = [];
        foreach ($botsToCreate as $bot) {
            $availableTokens[(string) $bot['token']] = true;
        }

        $commandsToImport = [];
        $seenGroups = [];
        foreach ($botCommands as $index => $commandGroup) {
            if (!is_array($commandGroup)) {
                Response::json(['ok' => false, 'error' => 'bot_commands[' . $index . '] должен быть объектом'], 400);
                return null;
            }

            $token = trim((string) ($commandGroup['bot_token'] ?? ''));
            $scope = BotApiParams::commandScope($commandGroup['scope'] ?? null);
            $languageCode = BotApiParams::languageCode($commandGroup['language_code'] ?? '');
            if ($token === '' || $scope === null || !isset($availableTokens[$token])) {
                Response::json(['ok' => false, 'error' => 'Некорректный bot_token в bot_commands'], 400);
                return null;
            }

            $groupKey = $token . "\n" . $this->botCommands->scopeKey($scope) . "\n" . $languageCode;
            if (isset($seenGroups[$groupKey])) {
                Response::json(['ok' => false, 'error' => 'Дубликат bot_commands scope/language'], 409);
                return null;
            }

            $commands = BotApiParams::commands($commandGroup['commands'] ?? null);
            if ($commands === null) {
                Response::json(['ok' => false, 'error' => 'Некорректные commands в bot_commands'], 400);
                return null;
            }

            $seenGroups[$groupKey] = true;
            $commandsToImport[] = [
                'bot_token' => $token,
                'scope' => $scope,
                'language_code' => $languageCode,
                'commands' => $commands,
            ];
        }

        return $commandsToImport;
    }

    /**
     * @param list<mixed> $chats
     * @param list<array<string, mixed>> $profilesToCreate
     */
    private function validFixtureChats(array $chats, array $profilesToCreate): bool {
        $profileChatTypes = [];
        foreach ($profilesToCreate as $profile) {
            $profileChatTypes[(int) $profile['chat_id']] = (string) $profile['chat_type'];
        }

        $seenChatIds = [];
        foreach ($chats as $index => $chat) {
            if (!is_array($chat)) {
                Response::json(['ok' => false, 'error' => 'chats[' . $index . '] должен быть объектом'], 400);
                return false;
            }

            $chatId = $this->intParam($chat['chat_id'] ?? 0, 0);
            $chatType = (string) ($chat['type'] ?? '');
            if ($chatId === 0 || !in_array($chatType, ['private', 'group', 'supergroup', 'channel'], true)) {
                Response::json(['ok' => false, 'error' => 'Некорректный chat payload'], 400);
                return false;
            }

            if (isset($seenChatIds[$chatId])) {
                Response::json(['ok' => false, 'error' => 'Дубликат chat_id в chats'], 409);
                return false;
            }

            if ($this->chats->findByChatId($chatId) !== null) {
                Response::json(['ok' => false, 'error' => 'Конфликт chat_id при импорте chats'], 409);
                return false;
            }

            if (isset($profileChatTypes[$chatId]) && $profileChatTypes[$chatId] !== $chatType) {
                Response::json(['ok' => false, 'error' => 'Конфликт chat type между chats и profiles'], 409);
                return false;
            }

            $seenChatIds[$chatId] = true;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $chat
     */
    private function fixtureChatTitle(array $chat): ?string {
        $title = trim((string) ($chat['title'] ?? ''));

        return $title === '' ? null : $title;
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
     * @param array<string, mixed> $chat
     * @return array<string, mixed>
     */
    private function exportChatPayload(array $chat): array {
        return [
            'chat_id' => (int) $chat['chat_id'],
            'type' => $chat['type'],
            'title' => $chat['title'],
        ];
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

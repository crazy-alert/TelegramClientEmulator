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
    private BotRepository $bots;
    private BotCommandRepository $botCommands;
    private ProfileRepository $profiles;
    private MessageRepository $messages;
    private UpdateRepository $updates;
    private DeliveryAttemptRepository $deliveryAttempts;
    private SettingsRepository $settings;
    private UpdateGenerator $updateGenerator;
    private HttpLogRepository $httpLogs;
    private HttpLogger $httpLogger;
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
        $this->httpLogs = new HttpLogRepository($this->logDir);
        $this->httpLogger = new HttpLogger($this->logDir);
        $this->view = new View($this->rootPath . '/templates');
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
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return;
        }

        $raw = $this->rawBody();
        if ($raw === '') {
            return;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $_POST = $data;
            }
            return;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($raw, $_POST);
            return;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            $_POST = $this->parseMultipartFormData($raw, $contentType);
        }
    }

    /**
     * Парсит текстовые поля multipart/form-data при отключенном enable_post_data_reading.
     *
     * @return array<string, string>
     */
    private function parseMultipartFormData(string $raw, string $contentType): array {
        if (preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches) !== 1) {
            return [];
        }

        $boundary = $matches[1] !== '' ? $matches[1] : trim($matches[2]);
        if ($boundary === '') {
            return [];
        }

        $fields = [];
        $parts = explode('--' . $boundary, $raw);

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            $part = preg_replace("/\r\n--\r\n?$/", '', $part) ?? $part;
            $part = rtrim($part, "\r\n");

            if ($part === '' || $part === '--') {
                continue;
            }

            $separator = str_contains($part, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
            [$headers, $body] = array_pad(explode($separator, $part, 2), 2, '');

            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bname="([^"]+)"/i', $headers, $nameMatches) !== 1) {
                continue;
            }

            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bfilename="/i', $headers) === 1) {
                continue;
            }

            $fields[$nameMatches[1]] = $body;
        }

        return $fields;
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
        // --- Чат ---

        if ($method === 'GET' && $path === '/chat') {
            $this->chatIndex();
            return;
        }

        if ($method === 'GET' && $path === '/chat/fragment') {
            $this->chatFragment();
            return;
        }

        if ($method === 'POST' && $path === '/chat/send') {
            $this->chatSend();
            return;
        }

        if ($method === 'POST' && $path === '/chat/callback') {
            $this->chatCallback();
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

        // --- Bot API маршруты (/bot{token}/...) ---

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getMe$#i', $path, $matches) === 1) {
            $this->getMe($matches[1]);
            return;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getUpdates$#i', $path, $matches) === 1) {
            $this->getUpdates($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendMessage$#i', $path, $matches) === 1) {
            $this->sendMessage($matches[1]);
            return;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getWebhookInfo$#i', $path, $matches) === 1) {
            $this->getWebhookInfo($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/setMyCommands$#i', $path, $matches) === 1) {
            $this->setMyCommands($matches[1]);
            return;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getMyCommands$#i', $path, $matches) === 1) {
            $this->getMyCommands($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/deleteMyCommands$#i', $path, $matches) === 1) {
            $this->deleteMyCommands($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/answerCallbackQuery$#i', $path, $matches) === 1) {
            $this->answerCallbackQuery($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/setWebhook$#i', $path, $matches) === 1) {
            $this->setWebhook($matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/deleteWebhook$#i', $path, $matches) === 1) {
            $this->deleteWebhook($matches[1]);
            return;
        }

        // Заглушка для неподдерживаемых методов Bot API
        if (preg_match('#^/bot([^/]+)/#', $path) === 1) {
            Response::json([
                'ok' => false,
                'error_code' => 501,
                'description' => 'Метод пока не поддерживается эмулятором',
            ], 501);
            return;
        }

        Response::json([
            'ok' => false,
            'error' => 'Маршрут не найден',
        ], 404);
    }

    // ----------------------------------------------------------------
    // Чат
    // ----------------------------------------------------------------

    private function chatIndex(): void {
        $this->render('chat/index', $this->chatViewData());
    }

    private function chatFragment(): void {
        $data = $this->chatViewData();

        $data['chatFragment'] = true;
        $this->renderPartial('chat/index', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function chatViewData(): array {
        $profile = $this->selectedUser();
        $bot = $this->selectedBot();

        $messages = [];
        $latestUpdate = null;
        $latestDeliveryAttempt = null;
        $pendingUpdateCount = 0;
        $botCommands = [];

        if ($profile !== null && $bot !== null) {
            $messages = $this->messages->findByDialog(
                (int) $bot['id'],
                (int) $profile['id'],
                (int) $profile['chat_id'],
            );
            $latestUpdate = $this->updates->findLatestByBot((int) $bot['id']);
            if ($latestUpdate !== null) {
                $latestDeliveryAttempt = $this->deliveryAttempts->findLatestByUpdate((int) $latestUpdate['id']);
            }
            $pendingUpdateCount = $this->updates->countPendingByBot((int) $bot['id']);
            $botCommands = $this->botCommands->allForBot((int) $bot['id']);
        }

        return [
            'title' => 'Чат',
            'profile' => $profile,
            'bot' => $bot,
            'messages' => $messages,
            'latestUpdate' => $latestUpdate,
            'latestDeliveryAttempt' => $latestDeliveryAttempt,
            'pendingUpdateCount' => $pendingUpdateCount,
            'botCommands' => $botCommands,
            'selectedProfileId' => (int) ($_GET['profile_id'] ?? 0),
            'selectedBotId' => (int) ($_GET['bot_id'] ?? 0),
        ];
    }

    private function chatSend(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $profile = $this->enabledUserById($profileId);
        $bot = $this->enabledBotById($botId);

        if ($profile === null || $bot === null) {
            Response::redirect('/chat');
            return;
        }

        $text = trim((string) ($_POST['text'] ?? ''));

        if ($text === '') {
            Response::redirect('/chat');
            return;
        }

        $chatId = (int) $profile['chat_id'];
        $botId = (int) $bot['id'];
        $profileId = (int) $profile['id'];

        // Сохраняем сообщение пользователя
        $this->messages->create([
            'bot_id' => $botId,
            'profile_id' => $profileId,
            'chat_id' => $chatId,
            'direction' => 'user',
            'text' => $text,
        ]);

        // Получаем только что созданное сообщение для генерации Update
        $allMessages = $this->messages->findByDialog($botId, $profileId, $chatId);
        $lastMessage = end($allMessages) ?: null;

        if ($lastMessage !== null) {
            // Генерируем Update payload
            $updatePayload = $this->updateGenerator->generate($lastMessage, $profile, $bot);
            $updatePayloadJson = json_encode($updatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            // Сохраняем update в очереди
            $createdUpdate = $this->updates->create([
                'bot_id' => $botId,
                'profile_id' => $profileId,
                'payload' => $updatePayloadJson,
                'delivery_mode' => $bot['delivery_mode'] ?? 'long_polling',
                'queue_state' => 'pending',
            ]);

            // Обновляем raw_payload у сообщения
            $this->database->pdo()->prepare(
                'UPDATE messages SET raw_payload = :payload WHERE id = :id'
            )->execute([
                'payload' => $createdUpdate['payload'],
                'id' => $lastMessage['id'],
            ]);

            if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
                $this->deliverWebhookUpdate($createdUpdate, $bot);
            }
        }

        Response::redirect('/chat?profile_id=' . $profileId . '&bot_id=' . $botId);
    }

    private function chatCallback(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $callbackData = (string) ($_POST['callback_data'] ?? '');
        $profile = $this->enabledUserById($profileId);
        $bot = $this->enabledBotById($botId);
        $message = $messageId > 0 ? $this->messages->find($messageId) : null;

        if (
            $profile === null
            || $bot === null
            || $message === null
            || $callbackData === ''
            || (int) $message['bot_id'] !== (int) $bot['id']
            || (int) $message['profile_id'] !== (int) $profile['id']
            || (string) $message['direction'] !== 'bot'
        ) {
            Response::redirect('/chat');
            return;
        }

        $updatePayload = $this->updateGenerator->generateCallbackQuery($message, $profile, $bot, $callbackData);
        $updatePayloadJson = json_encode($updatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $createdUpdate = $this->updates->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'payload' => $updatePayloadJson,
            'delivery_mode' => $bot['delivery_mode'] ?? 'long_polling',
            'queue_state' => 'pending',
        ]);

        if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
            $this->deliverWebhookUpdate($createdUpdate, $bot);
        }

        Response::redirect('/chat?profile_id=' . (int) $profile['id'] . '&bot_id=' . (int) $bot['id']);
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

        $this->deliverWebhookUpdate([
            'id' => (int) $update['id'],
            'update_id' => (int) $update['update_id'],
            'payload' => (string) $update['payload'],
        ], $bot);

        Response::redirect('/chat?profile_id=' . (int) $update['profile_id'] . '&bot_id=' . (int) $bot['id']);
    }

    /**
     * Возвращает выбранного пользователя из query string.
     *
     * @return array<string, mixed>|null
     */
    private function selectedUser(): ?array {
        return $this->enabledUserById((int) ($_GET['profile_id'] ?? 0));
    }

    /**
     * Возвращает выбранного бота из query string.
     *
     * @return array<string, mixed>|null
     */
    private function selectedBot(): ?array {
        return $this->enabledBotById((int) ($_GET['bot_id'] ?? 0));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enabledUserById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        $profile = $this->profiles->find($id);

        if ($profile === null || ((int) $profile['enabled']) !== 1) {
            return null;
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enabledBotById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        $bot = $this->bots->find($id);

        if ($bot === null || ((int) $bot['enabled']) !== 1) {
            return null;
        }

        return $bot;
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

            if (isset($seenChatIds[$chatId]) || $this->profiles->hasChatId($chatId)) {
                Response::json(['ok' => false, 'error' => 'Конфликт chat_id при импорте пользователя'], 409);
                return;
            }

            $seenUserIds[$userId] = true;
            $seenChatIds[$chatId] = true;
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

    // ----------------------------------------------------------------
    // Bot API методы
    // ----------------------------------------------------------------

    /**
     * Возвращает параметры Bot API запроса из query string и тела запроса.
     *
     * @return array<string, mixed>
     */
    private function botApiParams(): array {
        return array_replace($_GET, $_POST);
    }

    /**
     * GET /bot{token}/getMe — возвращает информацию о боте.
     */
    private function getMe(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        Response::json([
            'ok' => true,
            'result' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'],
                'username' => $bot['username'],
                'can_join_groups' => true,
                'can_read_all_group_messages' => false,
                'supports_inline_queries' => false,
            ],
        ]);
    }

    /**
     * GET|POST /bot{token}/getWebhookInfo — возвращает текущую настройку webhook.
     */
    private function getWebhookInfo(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $result = [
            'url' => (string) ($bot['webhook_url'] ?? ''),
            'has_custom_certificate' => false,
            'pending_update_count' => $this->updates->countPendingByBot((int) $bot['id']),
            'max_connections' => 40,
        ];
        $latestFailedAttempt = $this->deliveryAttempts->findLatestFailedByBot((int) $bot['id']);
        if ($latestFailedAttempt !== null) {
            $result['last_error_date'] = strtotime((string) $latestFailedAttempt['created_at']) ?: time();
            $result['last_error_message'] = $latestFailedAttempt['error'];
        }

        Response::json([
            'ok' => true,
            'result' => $result,
        ]);
    }

    /**
     * POST /bot{token}/sendMessage — сохраняет ответ бота в локальную историю чата.
     */
    private function sendMessage(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $params = $this->botApiParams();

        if (!array_key_exists('chat_id', $params) || trim((string) $params['chat_id']) === '') {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: parameter "chat_id" is required',
            ], 400);
            return;
        }

        if (!array_key_exists('text', $params) || trim((string) $params['text']) === '') {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: parameter "text" is required',
            ], 400);
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        if ($chatId === 0) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found',
            ], 400);
            return;
        }

        $profile = $this->profiles->findEnabledByChat($chatId);
        if ($profile === null) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: chat not found',
            ], 400);
            return;
        }

        $replyMarkup = $this->replyMarkupParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: object expected as reply markup',
            ], 400);
            return;
        }

        $message = $this->messages->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'chat_id' => $chatId,
            'direction' => 'bot',
            'text' => trim((string) $params['text']),
            'raw_payload' => $replyMarkup === null
                ? null
                : json_encode(['reply_markup' => $replyMarkup], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($message, $profile, $bot),
        ]);
    }

    private function setMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $params = $this->botApiParams();
        $commands = $this->commandsParam($params['commands'] ?? null);

        if ($commands === null) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: parameter "commands" is required',
            ], 400);
            return;
        }

        $this->botCommands->replaceForBot((int) $bot['id'], $commands);

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    private function getMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        Response::json([
            'ok' => true,
            'result' => $this->botCommands->allForBot((int) $bot['id']),
        ]);
    }

    private function deleteMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $this->botCommands->deleteForBot((int) $bot['id']);

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    private function answerCallbackQuery(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $params = $this->botApiParams();
        if (!array_key_exists('callback_query_id', $params) || trim((string) $params['callback_query_id']) === '') {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: parameter "callback_query_id" is required',
            ], 400);
            return;
        }

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    /**
     * GET|POST /bot{token}/getUpdates — отдаёт очередь Long Polling updates.
     */
    private function getUpdates(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
            Response::json([
                'ok' => false,
                'error_code' => 409,
                'description' => 'Conflict: can\'t use getUpdates method while webhook is active; use deleteWebhook to delete the webhook first',
            ], 409);
            return;
        }

        $params = $this->botApiParams();
        $offset = $this->intParam($params['offset'] ?? 0, 0);
        $limit = max(1, min(100, $this->intParam($params['limit'] ?? 100, 100)));
        $timeout = max(0, $this->intParam($params['timeout'] ?? 0, 0));
        $allowedUpdates = $this->allowedUpdatesParam($params['allowed_updates'] ?? null);
        $botId = (int) $bot['id'];
        $waitUntil = microtime(true) + min($timeout, 3);

        do {
            $updates = $this->pendingUpdates($botId, $offset, $limit);
            $result = $this->updatesResult($updates, $allowedUpdates);

            if ($result !== [] || $timeout === 0 || microtime(true) >= $waitUntil) {
                Response::json([
                    'ok' => true,
                    'result' => $result,
                ]);
                return;
            }

            usleep(250_000);
        } while (true);
    }

    /**
     * POST /bot{token}/setWebhook — сохраняет URL webhook для локальной доставки updates.
     */
    private function setWebhook(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $params = $this->botApiParams();

        if (!array_key_exists('url', $params)) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: parameter "url" is required',
            ], 400);
            return;
        }

        $url = trim((string) $params['url']);
        $secretToken = trim((string) ($params['secret_token'] ?? $params['webhook_secret_token'] ?? ''));

        if ($url === '') {
            $this->bots->setWebhook((int) $bot['id'], null, null);
            Response::json([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was deleted',
            ]);
            return;
        }

        if (!$this->isValidWebhookUrl($url)) {
            Response::json([
                'ok' => false,
                'error_code' => 400,
                'description' => 'Bad Request: invalid webhook URL',
            ], 400);
            return;
        }

        $this->bots->setWebhook(
            (int) $bot['id'],
            $url,
            $secretToken === '' ? null : $secretToken,
        );

        Response::json([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was set',
        ]);
    }

    /**
     * POST /bot{token}/deleteWebhook — удаляет webhook и возвращает бота в Long Polling.
     */
    private function deleteWebhook(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            Response::json([
                'ok' => false,
                'error_code' => 404,
                'description' => 'Бот не найден',
            ], 404);
            return;
        }

        $params = $this->botApiParams();

        $this->bots->setWebhook((int) $bot['id'], null, null);

        if ($this->isTruthyBotApiParam($params['drop_pending_updates'] ?? false)) {
            $this->updates->dropPendingByBot((int) $bot['id']);
        }

        Response::json([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was deleted',
        ]);
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

    /**
     * @return list<string>|null
     */
    private function allowedUpdatesParam(mixed $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value) || $value === []) {
            return null;
        }

        $allowedUpdates = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $allowedUpdates[] = $item;
            }
        }

        return $allowedUpdates === [] ? null : $allowedUpdates;
    }

    /**
     * @return list<array{command: string, description: string}>|null
     */
    private function commandsParam(mixed $value): ?array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $commands = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                return null;
            }

            $command = ltrim(trim((string) ($item['command'] ?? '')), '/');
            $description = trim((string) ($item['description'] ?? ''));

            if (preg_match('/^[a-z0-9_]{1,32}$/', $command) !== 1 || $description === '' || mb_strlen($description) > 256) {
                return null;
            }

            $commands[] = [
                'command' => $command,
                'description' => $description,
            ];
        }

        return count($commands) > 100 ? null : $commands;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function replyMarkupParam(mixed $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $allowedKeys = [
            'inline_keyboard',
            'keyboard',
            'resize_keyboard',
            'one_time_keyboard',
            'is_persistent',
            'input_field_placeholder',
            'selective',
            'remove_keyboard',
            'force_reply',
        ];

        return array_intersect_key($value, array_flip($allowedKeys));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingUpdates(int $botId, int $offset, int $limit): array {
        if ($offset < 0) {
            $updates = $this->updates->findLastPending($botId, abs($offset));
            if ($updates !== []) {
                $this->updates->confirmBeforeOffset($botId, (int) $updates[0]['update_id']);
            }

            return array_slice($updates, 0, $limit);
        }

        $this->updates->confirmBeforeOffset($botId, $offset);

        return $this->updates->findPending($botId, $limit, $offset);
    }

    /**
     * @param list<array<string, mixed>> $updates
     * @param list<string>|null $allowedUpdates
     * @return list<array<string, mixed>>
     */
    private function updatesResult(array $updates, ?array $allowedUpdates): array {
        $result = [];

        foreach ($updates as $update) {
            $payload = json_decode((string) $update['payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            $payload['update_id'] = (int) $update['update_id'];
            if ($allowedUpdates !== null && !$this->isAllowedUpdate($payload, $allowedUpdates)) {
                continue;
            }

            $result[] = $payload;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowedUpdates
     */
    private function isAllowedUpdate(array $payload, array $allowedUpdates): bool {
        foreach (array_keys($payload) as $key) {
            if ($key !== 'update_id' && in_array($key, $allowedUpdates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Отправляет update на webhook URL и сохраняет результат доставки.
     *
     * @param array{id: int, update_id: int, payload: string} $update
     * @param array<string, mixed> $bot
     */
    private function deliverWebhookUpdate(array $update, array $bot): void {
        $url = (string) $bot['webhook_url'];
        $headers = [
            'Content-Type: application/json',
        ];
        $secretToken = trim((string) ($bot['webhook_secret_token'] ?? ''));
        if ($secretToken !== '') {
            $headers[] = 'X-Telegram-Bot-Api-Secret-Token: ' . $secretToken;
        }

        $startedAt = microtime(true);
        $responseBody = null;
        $responseHeaders = [];
        $responseStatus = null;
        $error = null;

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $update['payload'],
                'ignore_errors' => true,
                'timeout' => $this->webhookTimeoutSeconds(),
            ],
        ]);

        try {
            $response = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header ?? [];
            $responseStatus = $this->httpStatusFromHeaders($responseHeaders);
            $responseBody = $response === false ? null : $response;

            if ($response === false) {
                $error = 'Webhook request failed';
            } elseif ($responseStatus === null || $responseStatus < 200 || $responseStatus >= 300) {
                $error = 'Webhook returned HTTP ' . ($responseStatus ?? 'unknown');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $delivered = $error === null;

        $this->deliveryAttempts->create([
            'update_row_id' => $update['id'],
            'bot_id' => (int) $bot['id'],
            'webhook_url' => $url,
            'request_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_body' => $update['payload'],
            'response_status' => $responseStatus,
            'response_headers' => json_encode($responseHeaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'error' => $error,
        ]);
        $this->updates->markWebhookDelivery((int) $update['id'], $delivered);
    }

    /**
     * @param list<string> $headers
     */
    private function httpStatusFromHeaders(array $headers): ?int {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
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

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $bot
     * @return array<string, mixed>
     */
    private function botMessagePayload(array $message, array $profile, array $bot): array {
        $payload = [
            'message_id' => (int) $message['telegram_message_id'],
            'from' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'],
                'username' => $bot['username'],
            ],
            'chat' => [
                'id' => (int) $profile['chat_id'],
                'type' => $profile['chat_type'] ?? 'private',
                'username' => $profile['username'] ?? '',
                'first_name' => $profile['first_name'] ?? '',
                'last_name' => $profile['last_name'] ?? '',
            ],
            'date' => strtotime((string) $message['created_at']) ?: time(),
            'text' => $message['text'],
        ];

        $rawPayload = json_decode((string) ($message['raw_payload'] ?? ''), true);
        if (is_array($rawPayload) && isset($rawPayload['reply_markup']) && is_array($rawPayload['reply_markup'])) {
            $payload['reply_markup'] = $rawPayload['reply_markup'];
        }

        return $payload;
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

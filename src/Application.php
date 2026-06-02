<?php

declare(strict_types=1);

namespace App;

use Throwable;

final class Application {

    private Database $database;
    private BotRepository $bots;
    private ProfileRepository $profiles;
    private MessageRepository $messages;
    private UpdateRepository $updates;
    private DeliveryAttemptRepository $deliveryAttempts;
    private UpdateGenerator $updateGenerator;
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
        $this->profiles = new ProfileRepository($this->database->pdo());
        $this->messages = new MessageRepository($this->database->pdo());
        $this->updates = new UpdateRepository($this->database->pdo());
        $this->deliveryAttempts = new DeliveryAttemptRepository($this->database->pdo());
        $this->updateGenerator = new UpdateGenerator();
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

        if ($method === 'POST' && $path === '/chat/send') {
            $this->chatSend();
            return;
        }

        // --- Панель и health ---

        if ($method === 'GET' && in_array($path, ['/', '/index.php'], true)) {
            $this->dashboard();
            return;
        }

        if ($method === 'GET' && $path === '/health') {
            $this->health();
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
            $this->bots->create($_POST);
            Response::redirect('/bots');
            return;
        }

        if ($method === 'GET' && preg_match('#^/bots/(\d+)/edit$#', $path, $matches) === 1) {
            $this->botForm((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/bots/(\d+)$#', $path, $matches) === 1) {
            $this->bots->update((int) $matches[1], $_POST);
            Response::redirect('/bots');
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
            $this->profiles->create($_POST);
            Response::redirect('/profiles');
            return;
        }

        if ($method === 'GET' && preg_match('#^/profiles/(\d+)/edit$#', $path, $matches) === 1) {
            $this->profileForm((int) $matches[1]);
            return;
        }

        if ($method === 'POST' && preg_match('#^/profiles/(\d+)$#', $path, $matches) === 1) {
            $this->profiles->update((int) $matches[1], $_POST);
            Response::redirect('/profiles');
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
        $profile = $this->selectedUser();
        $bot = $this->selectedBot();

        $messages = [];
        $latestUpdate = null;
        $latestDeliveryAttempt = null;
        $pendingUpdateCount = 0;

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
        }

        $this->render('chat/index', [
            'title' => 'Чат',
            'profile' => $profile,
            'bot' => $bot,
            'messages' => $messages,
            'latestUpdate' => $latestUpdate,
            'latestDeliveryAttempt' => $latestDeliveryAttempt,
            'pendingUpdateCount' => $pendingUpdateCount,
            'selectedProfileId' => (int) ($_GET['profile_id'] ?? 0),
            'selectedBotId' => (int) ($_GET['bot_id'] ?? 0),
        ]);
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
        ]);
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
        ]);
    }

    private function profilesIndex(): void {
        $this->render('profiles/index', [
            'title' => 'Пользователи',
            'users' => $this->profiles->all(),
        ]);
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
        ]);
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

        $message = $this->messages->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'chat_id' => $chatId,
            'direction' => 'bot',
            'text' => trim((string) $params['text']),
        ]);

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($message, $profile, $bot),
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
        $timeoutMs = $this->intParam(getenv('WEBHOOK_TIMEOUT_MS') ?: 10000, 10000);

        return max(1, min(60, (int) ceil($timeoutMs / 1000)));
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $bot
     * @return array<string, mixed>
     */
    private function botMessagePayload(array $message, array $profile, array $bot): array {
        return [
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

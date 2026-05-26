<?php

declare(strict_types=1);

namespace App;

use Throwable;

final readonly class Application {

    private Database $database;
    private BotRepository $bots;
    private ProfileRepository $profiles;
    private MessageRepository $messages;
    private UpdateRepository $updates;
    private UpdateGenerator $updateGenerator;
    private View $view;

    public function __construct(
        private string $rootPath,
        private string $dataDir,
    ) {
        $this->database = new Database($this->dataDir);
        $this->bots = new BotRepository($this->database->pdo());
        $this->profiles = new ProfileRepository($this->database->pdo());
        $this->messages = new MessageRepository($this->database->pdo());
        $this->updates = new UpdateRepository($this->database->pdo());
        $this->updateGenerator = new UpdateGenerator();
        $this->view = new View($this->rootPath . '/templates');
    }

    /**
     * Возвращает сырое тело запроса (используется для Bot API методов с JSON body).
     */
    public function rawBody(): string {
        return (string) file_get_contents('php://input');
    }

    public function handle(string $method, string $path): void {
        try {
            $this->boot();
            $this->route($method, rtrim($path, '/') ?: '/');
        } catch (Throwable $exception) {
            Response::json([
                'ok' => false,
                'error' => 'Внутренняя ошибка приложения',
                'details' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Возвращает активный профиль из cookie или null, если не выбран.
     *
     * @return array<string, mixed>|null
     */
    public function activeProfile(): ?array {
        $id = isset($_COOKIE['active_profile_id']) ? (int) $_COOKIE['active_profile_id'] : 0;

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
     * Возвращает активного бота из cookie или null, если не выбран.
     *
     * @return array<string, mixed>|null
     */
    public function activeBot(): ?array {
        $id = isset($_COOKIE['active_bot_id']) ? (int) $_COOKIE['active_bot_id'] : 0;

        if ($id <= 0) {
            return null;
        }

        $bot = $this->bots->find($id);

        if ($bot === null || ((int) $bot['enabled']) !== 1) {
            return null;
        }

        return $bot;
    }

    private function route(string $method, string $path): void {
        // --- Переключатели активного профиля и бота ---

        if ($method === 'POST' && $path === '/select-profile') {
            $this->selectProfile();
            return;
        }

        if ($method === 'POST' && $path === '/select-bot') {
            $this->selectBot();
            return;
        }

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

        // --- Профили ---

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

        if ($method === 'GET' && preg_match('#^/bot([^/]+)/getMe$#', $path, $matches) === 1) {
            $this->getMe($matches[1]);
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
    // Переключатели профиля и бота (cookie)
    // ----------------------------------------------------------------

    private function selectProfile(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        setcookie('active_profile_id', (string) $profileId, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    private function selectBot(): void {
        $botId = (int) ($_POST['bot_id'] ?? 0);
        setcookie('active_bot_id', (string) $botId, [
            'expires' => time() + 86400 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }

    // ----------------------------------------------------------------
    // Чат
    // ----------------------------------------------------------------

    private function chatIndex(): void {
        $profile = $this->activeProfile();
        $bot = $this->activeBot();

        $messages = [];
        $latestUpdate = null;

        if ($profile !== null && $bot !== null) {
            $messages = $this->messages->findByDialog(
                (int) $bot['id'],
                (int) $profile['id'],
                (int) $profile['chat_id'],
            );
            $latestUpdate = $this->updates->findLatestByBot((int) $bot['id']);
        }

        $this->render('chat/index', [
            'title' => 'Чат',
            'profile' => $profile,
            'bot' => $bot,
            'messages' => $messages,
            'latestUpdate' => $latestUpdate,
        ]);
    }

    private function chatSend(): void {
        $profile = $this->activeProfile();
        $bot = $this->activeBot();

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
            $this->updates->create([
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
                'payload' => $updatePayloadJson,
                'id' => $lastMessage['id'],
            ]);
        }

        Response::redirect('/chat');
    }

    // ----------------------------------------------------------------
    // Инфраструктура
    // ----------------------------------------------------------------

    /**
     * Рендерит шаблон, автоматически добавляя allProfiles и allBots для переключателей в шапке.
     *
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void {
        $data['allProfiles'] = $this->profiles->all();
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
            'profiles' => $this->profiles->all(),
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
        ]);
    }

    private function profilesIndex(): void {
        $this->render('profiles/index', [
            'title' => 'Профили',
            'profiles' => $this->profiles->all(),
        ]);
    }

    private function profileForm(?int $id = null): void {
        $profile = $id === null ? null : $this->profiles->find($id);

        if ($id !== null && $profile === null) {
            Response::json(['ok' => false, 'error' => 'Профиль не найден'], 404);
            return;
        }

        $this->render('profiles/form', [
            'title' => $profile === null ? 'Новый профиль' : 'Редактирование профиля',
            'profile' => $profile,
            'bots' => $this->bots->all(),
        ]);
    }

    // ----------------------------------------------------------------
    // Bot API методы
    // ----------------------------------------------------------------

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

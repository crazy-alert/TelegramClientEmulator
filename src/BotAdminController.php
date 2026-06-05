<?php

declare(strict_types=1);

namespace App;

/**
 * UI-маршруты управления локальными ботами.
 */
final readonly class BotAdminController {

    public function __construct(
        private BotRepository $bots,
        private ProfileRepository $profiles,
        private View $view,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if ($method === 'GET' && $path === '/bots') {
            $this->index();
            return true;
        }

        if ($method === 'GET' && $path === '/bots/new') {
            $this->form();
            return true;
        }

        if ($method === 'POST' && $path === '/bots') {
            $this->create();
            return true;
        }

        if ($method === 'GET' && preg_match('#^/bots/(\d+)/edit$#', $path, $matches) === 1) {
            $this->form((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bots/(\d+)$#', $path, $matches) === 1) {
            $this->update((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bots/(\d+)/delete$#', $path, $matches) === 1) {
            $this->bots->delete((int) $matches[1]);
            Response::redirect('/bots');
            return true;
        }

        return false;
    }

    private function index(): void {
        $this->render('bots/index', [
            'title' => 'Боты',
            'bots' => $this->bots->all(),
        ]);
    }

    private function form(?int $id = null): void {
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

    private function create(): void {
        $errors = $this->validateForm($_POST);

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

    private function update(int $id): void {
        $bot = $this->bots->find($id);

        if ($bot === null) {
            Response::json(['ok' => false, 'error' => 'Бот не найден'], 404);
            return;
        }

        $errors = $this->validateForm($_POST);
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

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateForm(array $data): array {
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

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->render($template, $data);
    }
}

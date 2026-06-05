<?php

declare(strict_types=1);

namespace App;

/**
 * UI-маршруты управления локальными пользователями.
 */
final readonly class ProfileAdminController {

    public function __construct(
        private ProfileRepository $profiles,
        private BotRepository $bots,
        private View $view,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if ($method === 'GET' && $path === '/profiles') {
            $this->index();
            return true;
        }

        if ($method === 'GET' && $path === '/profiles/new') {
            $this->form();
            return true;
        }

        if ($method === 'POST' && $path === '/profiles') {
            $this->create();
            return true;
        }

        if ($method === 'GET' && preg_match('#^/profiles/(\d+)/edit$#', $path, $matches) === 1) {
            $this->form((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/profiles/(\d+)$#', $path, $matches) === 1) {
            $this->update((int) $matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/profiles/(\d+)/delete$#', $path, $matches) === 1) {
            $this->profiles->delete((int) $matches[1]);
            Response::redirect('/profiles');
            return true;
        }

        return false;
    }

    private function index(): void {
        $this->render('profiles/index', [
            'title' => 'Пользователи',
            'users' => $this->profiles->all(),
        ]);
    }

    private function form(?int $id = null): void {
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

    private function create(): void {
        $errors = $this->validateForm($_POST);

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

    private function update(int $id): void {
        $profile = $this->profiles->find($id);

        if ($profile === null) {
            Response::json(['ok' => false, 'error' => 'Пользователь не найден'], 404);
            return;
        }

        $errors = $this->validateForm($_POST);
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
    private function validateForm(array $data): array {
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
     */
    private function render(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->render($template, $data);
    }
}

<?php

declare(strict_types=1);

namespace App;

use Throwable;

final readonly class Application {

    private Database $database;
    private BotRepository $bots;
    private ProfileRepository $profiles;
    private View $view;

    public function __construct(
        private string $rootPath,
        private string $dataDir,
    ) {
        $this->database = new Database($this->dataDir);
        $this->bots = new BotRepository($this->database->pdo());
        $this->profiles = new ProfileRepository($this->database->pdo());
        $this->view = new View($this->rootPath . '/templates');
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

    private function route(string $method, string $path): void {
        if ($method === 'GET' && in_array($path, ['/', '/index.php'], true)) {
            $this->dashboard();
            return;
        }

        if ($method === 'GET' && $path === '/health') {
            $this->health();
            return;
        }

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

        Response::json([
            'ok' => false,
            'error' => 'Маршрут не найден',
        ], 404);
    }

    private function boot(): void {
        $runner = new MigrationRunner(
            pdo: $this->database->pdo(),
            migrationsPath: $this->rootPath . '/migrations',
        );

        $runner->run();
    }

    private function dashboard(): void {
        $this->view->render('dashboard', [
            'title' => 'Панель',
            'bots' => $this->bots->all(),
            'profiles' => $this->profiles->all(),
            'databasePath' => $this->database->path(),
        ]);
    }

    private function botsIndex(): void {
        $this->view->render('bots/index', [
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

        $this->view->render('bots/form', [
            'title' => $bot === null ? 'Новый бот' : 'Редактирование бота',
            'bot' => $bot,
        ]);
    }

    private function profilesIndex(): void {
        $this->view->render('profiles/index', [
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

        $this->view->render('profiles/form', [
            'title' => $profile === null ? 'Новый профиль' : 'Редактирование профиля',
            'profile' => $profile,
            'bots' => $this->bots->all(),
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

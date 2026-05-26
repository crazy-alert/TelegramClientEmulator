<?php

declare(strict_types=1);

/**
 * Front controller приложения Telegram Bot Emulator.
 *
 * Подключает все необходимые классы, инициализирует Application
 * и передаёт ему входящий HTTP-запрос.
 */

use App\Application;

require dirname(__DIR__) . '/src/Application.php';
require dirname(__DIR__) . '/src/BotRepository.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/MigrationRunner.php';
require dirname(__DIR__) . '/src/ProfileRepository.php';
require dirname(__DIR__) . '/src/Response.php';
require dirname(__DIR__) . '/src/View.php';

if (!function_exists('e')) {
    /**
     * Экранирует значение для безопасного вывода в HTML.
     *
     * Используется в шаблонах для защиты от XSS.
     */
    function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

$application = new Application(
    rootPath: dirname(__DIR__),
    dataDir: getenv('DATA_DIR') ?: dirname(__DIR__) . '/data',
);

$application->handle(
    method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
    path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
);

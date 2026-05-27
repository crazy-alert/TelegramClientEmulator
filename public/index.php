<?php

declare(strict_types=1);

// Буферизация вывода с самого начала — перехватывает warning'и
// встроенного PHP-сервера (multipart/form-data и др.)
ob_start();

/**
 * Front controller приложения Telegram Bot Emulator.
 *
 * Подключает все необходимые классы, инициализирует Application
 * и передаёт ему входящий HTTP-запрос.
 */

// Не выводить ошибки в output (чтобы не ломать JSON-ответы)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Буферизация вывода — перехватывает warning'и встроенного сервера
// (дублирует php.ini на случай, если конфиг не загрузился)
ini_set('output_buffering', '4096');

use App\Application;

require dirname(__DIR__) . '/src/Application.php';
require dirname(__DIR__) . '/src/BotRepository.php';
require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/DeliveryAttemptRepository.php';
require dirname(__DIR__) . '/src/HttpLogger.php';
require dirname(__DIR__) . '/src/MessageRepository.php';
require dirname(__DIR__) . '/src/MigrationRunner.php';
require dirname(__DIR__) . '/src/ProfileRepository.php';
require dirname(__DIR__) . '/src/Response.php';
require dirname(__DIR__) . '/src/UpdateGenerator.php';
require dirname(__DIR__) . '/src/UpdateRepository.php';
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
    logDir: getenv('LOG_DIR') ?: dirname(__DIR__) . '/var/logs',
);

$application->handle(
    method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
    path: parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
);

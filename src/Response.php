<?php

declare(strict_types=1);

namespace App;

final class Response {

    /**
     * Отправляет JSON-ответ и завершает обработку текущего запроса.
     *
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): void {
        // Очищаем буфер от возможных warning'ов встроенного PHP-сервера
        if (ob_get_level() > 0) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function redirect(string $location): void {
        if (ob_get_level() > 0) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code(303);
            header('Location: ' . $location);
        }
    }
}

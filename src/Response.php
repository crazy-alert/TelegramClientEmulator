<?php

declare(strict_types=1);

namespace App;

final class Response
{
    /**
     * Отправляет JSON-ответ и завершает обработку текущего запроса.
     *
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function redirect(string $location): void
    {
        http_response_code(303);
        header('Location: ' . $location);
    }
}

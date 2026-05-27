<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для попыток webhook-доставки updates.
 */
final readonly class DeliveryAttemptRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Сохраняет попытку webhook-доставки.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO delivery_attempts (
                update_row_id, bot_id, webhook_url, request_headers, request_body,
                response_status, response_headers, response_body, duration_ms, error
            ) VALUES (
                :update_row_id, :bot_id, :webhook_url, :request_headers, :request_body,
                :response_status, :response_headers, :response_body, :duration_ms, :error
            )'
        );

        $statement->execute([
            'update_row_id' => (int) $data['update_row_id'],
            'bot_id' => (int) $data['bot_id'],
            'webhook_url' => $data['webhook_url'],
            'request_headers' => $data['request_headers'] ?? null,
            'request_body' => $data['request_body'],
            'response_status' => $data['response_status'] ?? null,
            'response_headers' => $data['response_headers'] ?? null,
            'response_body' => $data['response_body'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'error' => $data['error'] ?? null,
        ]);
    }

    /**
     * Возвращает последнюю попытку доставки для update.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByUpdate(int $updateRowId): ?array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM delivery_attempts
            WHERE update_row_id = :update_row_id
            ORDER BY id DESC
            LIMIT 1'
        );
        $statement->execute(['update_row_id' => $updateRowId]);
        $attempt = $statement->fetch();

        return $attempt === false ? null : $attempt;
    }

    /**
     * Возвращает последнюю неуспешную попытку доставки для бота.
     *
     * @return array<string, mixed>|null
     */
    public function findLatestFailedByBot(int $botId): ?array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM delivery_attempts
            WHERE bot_id = :bot_id AND error IS NOT NULL
            ORDER BY id DESC
            LIMIT 1'
        );
        $statement->execute(['bot_id' => $botId]);
        $attempt = $statement->fetch();

        return $attempt === false ? null : $attempt;
    }
}

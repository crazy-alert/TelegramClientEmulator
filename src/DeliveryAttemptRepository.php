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
     * Возвращает список попыток доставки с данными update, бота и пользователя.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithContext(?int $botId = null, ?int $updateId = null, int $limit = 100): array {
        $where = [];
        $params = [
            'limit' => max(1, min(500, $limit)),
        ];

        if ($botId !== null && $botId > 0) {
            $where[] = 'delivery_attempts.bot_id = :bot_id';
            $params['bot_id'] = $botId;
        }

        if ($updateId !== null && $updateId > 0) {
            $where[] = 'updates.update_id = :update_id';
            $params['update_id'] = $updateId;
        }

        $sql = 'SELECT
                delivery_attempts.*,
                updates.update_id,
                updates.profile_id,
                updates.queue_state,
                bots.username AS bot_username,
                bots.display_name AS bot_display_name,
                profiles.username AS profile_username,
                profiles.first_name AS profile_first_name,
                profiles.last_name AS profile_last_name
            FROM delivery_attempts
            INNER JOIN updates ON updates.id = delivery_attempts.update_row_id
            INNER JOIN bots ON bots.id = delivery_attempts.bot_id
            INNER JOIN profiles ON profiles.id = updates.profile_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY delivery_attempts.id DESC LIMIT :limit';

        $statement = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->execute();

        return $statement->fetchAll();
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

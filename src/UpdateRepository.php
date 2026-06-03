<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с очередью updates в SQLite.
 *
 * Хранит сгенерированные Telegram-like Update объекты.
 */
final readonly class UpdateRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Сохраняет новый update в очереди.
     *
     * update_id вычисляется как 100_000_000 + автоинкрементный id строки.
     *
     * @param array<string, mixed> $data Поля: bot_id, profile_id, payload, delivery_mode, queue_state.
     * @return array{id: int, update_id: int, payload: string}
     */
    public function create(array $data): array {
        $statement = $this->pdo->prepare(
            'INSERT INTO updates (bot_id, profile_id, update_id, payload, delivery_mode, queue_state)
            VALUES (:bot_id, :profile_id, :update_id, :payload, :delivery_mode, :queue_state)'
        );

        // Временный update_id = 0, будет перезаписан после получения реального id строки
        $statement->execute([
            'bot_id' => (int) $data['bot_id'],
            'profile_id' => (int) $data['profile_id'],
            'update_id' => 0,
            'payload' => $data['payload'],
            'delivery_mode' => $data['delivery_mode'],
            'queue_state' => $data['queue_state'] ?? 'pending',
        ]);

        $rowId = (int) $this->pdo->lastInsertId();
        $updateId = 100_000_000 + $rowId;
        $payload = $this->payloadWithUpdateId((string) $data['payload'], $updateId);

        $update = $this->pdo->prepare('UPDATE updates SET update_id = :update_id, payload = :payload WHERE id = :id');
        $update->execute([
            'update_id' => $updateId,
            'payload' => $payload,
            'id' => $rowId,
        ]);

        return [
            'id' => $rowId,
            'update_id' => $updateId,
            'payload' => $payload,
        ];
    }

    /**
     * Возвращает последний update для указанного бота (для inspector).
     *
     * @return array<string, mixed>|null
     */
    public function findLatestByBot(int $botId): ?array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM updates WHERE bot_id = :bot_id ORDER BY id DESC LIMIT 1'
        );
        $statement->execute(['bot_id' => $botId]);

        $update = $statement->fetch();

        return $update === false ? null : $update;
    }

    /**
     * Возвращает update по внутреннему идентификатору строки.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM updates WHERE id = :id');
        $statement->execute(['id' => $id]);
        $update = $statement->fetch();

        return $update === false ? null : $update;
    }

    /**
     * Возвращает updates с данными бота и пользователя для UI-списка.
     *
     * @return list<array<string, mixed>>
     */
    public function allWithContext(?int $botId = null, ?int $profileId = null, ?int $updateId = null, ?string $queueState = null, int $limit = 100): array {
        $conditions = [];
        $params = [];

        if ($botId !== null) {
            $conditions[] = 'u.bot_id = :bot_id';
            $params['bot_id'] = $botId;
        }

        if ($profileId !== null) {
            $conditions[] = 'u.profile_id = :profile_id';
            $params['profile_id'] = $profileId;
        }

        if ($updateId !== null) {
            $conditions[] = 'u.update_id = :update_id';
            $params['update_id'] = $updateId;
        }

        if ($queueState !== null) {
            $conditions[] = 'u.queue_state = :queue_state';
            $params['queue_state'] = $queueState;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo->prepare(
            'SELECT
                u.*,
                b.username AS bot_username,
                b.display_name AS bot_display_name,
                p.username AS profile_username,
                p.first_name AS profile_first_name,
                p.last_name AS profile_last_name,
                p.chat_id AS profile_chat_id,
                p.chat_type AS profile_chat_type
            FROM updates u
            INNER JOIN bots b ON b.id = u.bot_id
            INNER JOIN profiles p ON p.id = u.profile_id
            ' . $where . '
            ORDER BY u.id DESC
            LIMIT :limit'
        );

        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Возвращает неподтверждённые updates для бота.
     *
     * @return list<array<string, mixed>>
     */
    public function findPending(int $botId, int $limit = 100, int $offset = 0): array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM updates
            WHERE bot_id = :bot_id AND queue_state = \'pending\' AND update_id >= :offset
            ORDER BY update_id ASC
            LIMIT :limit'
        );
        $statement->execute([
            'bot_id' => $botId,
            'offset' => $offset,
            'limit' => $limit,
        ]);

        return $statement->fetchAll();
    }

    /**
     * Возвращает последние неподтверждённые updates для отрицательного offset.
     *
     * @return list<array<string, mixed>>
     */
    public function findLastPending(int $botId, int $limit): array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM updates
            WHERE bot_id = :bot_id AND queue_state = \'pending\'
            ORDER BY update_id DESC
            LIMIT :limit'
        );
        $statement->execute([
            'bot_id' => $botId,
            'limit' => $limit,
        ]);

        return array_reverse($statement->fetchAll());
    }

    /**
     * Подтверждает updates с `update_id` меньше переданного offset.
     */
    public function confirmBeforeOffset(int $botId, int $offset): void {
        if ($offset <= 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE updates
            SET queue_state = \'confirmed\', confirmed_at = CURRENT_TIMESTAMP
            WHERE bot_id = :bot_id AND queue_state = \'pending\' AND update_id < :offset'
        );
        $statement->execute([
            'bot_id' => $botId,
            'offset' => $offset,
        ]);
    }

    /**
     * Считает неподтверждённые updates бота для интерфейса.
     */
    public function countPendingByBot(int $botId): int {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM updates WHERE bot_id = :bot_id AND queue_state = \'pending\''
        );
        $statement->execute(['bot_id' => $botId]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Отмечает результат webhook-доставки update.
     */
    public function markWebhookDelivery(int $id, bool $delivered): void {
        $statement = $this->pdo->prepare(
            'UPDATE updates
            SET queue_state = :queue_state,
                delivered_at = :delivered_at
            WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'queue_state' => $delivered ? 'delivered' : 'failed',
            'delivered_at' => $delivered ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * Удаляет неподтверждённые updates бота при `drop_pending_updates`.
     */
    public function dropPendingByBot(int $botId): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM updates WHERE bot_id = :bot_id AND queue_state = \'pending\''
        );
        $statement->execute(['bot_id' => $botId]);
    }

    public function deleteByDialog(int $botId, int $profileId): int {
        $statement = $this->pdo->prepare(
            'DELETE FROM updates WHERE bot_id = :bot_id AND profile_id = :profile_id'
        );
        $statement->execute([
            'bot_id' => $botId,
            'profile_id' => $profileId,
        ]);

        return $statement->rowCount();
    }

    public function deletePendingAndConfirmedByBot(int $botId): int {
        $statement = $this->pdo->prepare(
            'DELETE FROM updates
            WHERE bot_id = :bot_id AND queue_state IN (\'pending\', \'confirmed\')'
        );
        $statement->execute(['bot_id' => $botId]);

        return $statement->rowCount();
    }

    private function payloadWithUpdateId(string $payload, int $updateId): string {
        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return $payload;
        }

        $decoded['update_id'] = $updateId;

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $payload;
    }
}

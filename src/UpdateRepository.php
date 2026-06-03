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

    private function payloadWithUpdateId(string $payload, int $updateId): string {
        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return $payload;
        }

        $decoded['update_id'] = $updateId;

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $payload;
    }
}

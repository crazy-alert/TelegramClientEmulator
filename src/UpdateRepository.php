<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с очередью updates в SQLite.
 *
 * Хранит сгенерированные Telegram-like Update объекты.
 * На Этапе 3 будет расширен методами для Long Polling (findPending, confirmByOffset).
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
     */
    public function create(array $data): void {
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

        $update = $this->pdo->prepare('UPDATE updates SET update_id = :update_id WHERE id = :id');
        $update->execute([
            'update_id' => $updateId,
            'id' => $rowId,
        ]);
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
     * Возвращает неподтверждённые updates для бота (заготовка для Этапа 3 — Long Polling).
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
     * Удаляет неподтверждённые updates бота при `drop_pending_updates`.
     */
    public function dropPendingByBot(int $botId): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM updates WHERE bot_id = :bot_id AND queue_state = \'pending\''
        );
        $statement->execute(['bot_id' => $botId]);
    }
}

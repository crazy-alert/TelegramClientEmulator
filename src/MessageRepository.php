<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с сообщениями чата в SQLite.
 *
 * Хранит историю диалога между пользователем и ботом.
 * Поддерживает два направления: 'user' (от пользователя) и 'bot' (ответ бота на Этапе 5).
 */
final readonly class MessageRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Возвращает историю диалога для пары бот-пользователь, отсортированную по времени (старые сверху).
     *
     * @return list<array<string, mixed>>
     */
    public function findByDialog(int $botId, int $profileId, int $chatId): array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM messages
            WHERE bot_id = :bot_id AND profile_id = :profile_id AND chat_id = :chat_id
            ORDER BY created_at ASC, id ASC'
        );
        $statement->execute([
            'bot_id' => $botId,
            'profile_id' => $profileId,
            'chat_id' => $chatId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * Создаёт новое сообщение с автоинкрементным telegram_message_id в пределах диалога.
     *
     * @param array<string, mixed> $data Поля: bot_id, profile_id, chat_id, direction, text, raw_payload (опционально).
     *
     * @return array<string, mixed>
     */
    public function create(array $data): array {
        $nextMessageId = $this->nextMessageId(
            (int) $data['bot_id'],
            (int) $data['chat_id'],
        );

        $statement = $this->pdo->prepare(
            'INSERT INTO messages (bot_id, profile_id, chat_id, telegram_message_id, direction, text, raw_payload)
            VALUES (:bot_id, :profile_id, :chat_id, :telegram_message_id, :direction, :text, :raw_payload)'
        );

        $statement->execute([
            'bot_id' => (int) $data['bot_id'],
            'profile_id' => (int) $data['profile_id'],
            'chat_id' => (int) $data['chat_id'],
            'telegram_message_id' => $nextMessageId,
            'direction' => $data['direction'],
            'text' => $data['text'],
            'raw_payload' => $data['raw_payload'] ?? null,
        ]);

        return $this->find((int) $this->pdo->lastInsertId()) ?? [
            'id' => (int) $this->pdo->lastInsertId(),
            'bot_id' => (int) $data['bot_id'],
            'profile_id' => (int) $data['profile_id'],
            'chat_id' => (int) $data['chat_id'],
            'telegram_message_id' => $nextMessageId,
            'direction' => $data['direction'],
            'text' => $data['text'],
            'raw_payload' => $data['raw_payload'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Находит сообщение по внутреннему идентификатору.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM messages WHERE id = :id');
        $statement->execute(['id' => $id]);
        $message = $statement->fetch();

        return $message === false ? null : $message;
    }

    /**
     * Вычисляет следующий telegram_message_id для пары бот-чат.
     */
    private function nextMessageId(int $botId, int $chatId): int {
        $statement = $this->pdo->prepare(
            'SELECT COALESCE(MAX(telegram_message_id), 0) + 1
            FROM messages
            WHERE bot_id = :bot_id AND chat_id = :chat_id'
        );
        $statement->execute([
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);

        return (int) $statement->fetchColumn();
    }
}

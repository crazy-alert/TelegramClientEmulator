<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Read-only доступ к нормализованной модели Telegram chat и membership.
 */
final readonly class ChatRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByChatId(int $chatId): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM chats WHERE chat_id = :chat_id');
        $statement->execute(['chat_id' => $chatId]);
        $chat = $statement->fetch();

        return $chat === false ? null : $chat;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function membersByChatId(int $chatId): array {
        $statement = $this->pdo->prepare(
            'SELECT
                profiles.*,
                chat_members.role AS chat_role,
                chats.chat_id AS member_chat_id,
                chats.type AS member_chat_type,
                chats.title AS member_chat_title
            FROM chat_members
            INNER JOIN chats ON chats.id = chat_members.chat_row_id
            INNER JOIN profiles ON profiles.id = chat_members.profile_id
            WHERE chats.chat_id = :chat_id
            ORDER BY profiles.id ASC'
        );
        $statement->execute(['chat_id' => $chatId]);

        return $statement->fetchAll();
    }
}

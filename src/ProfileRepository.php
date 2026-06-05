<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с пользователями в SQLite.
 *
 * Предоставляет CRUD-операции для локального пользователя:
 * создание, чтение (всех c JOIN ботов и по id), обновление и удаление.
 */
final readonly class ProfileRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Возвращает список всех пользователей,
     * отсортированный по дате создания (новые сверху).
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array {
        $statement = $this->pdo->query(
            'SELECT profiles.*, chats.title AS chat_title
            FROM profiles
            LEFT JOIN chats ON chats.chat_id = profiles.chat_id
            ORDER BY profiles.created_at DESC, profiles.id DESC'
        );

        return $statement->fetchAll();
    }

    /**
     * Находит пользователя по его внутреннему идентификатору.
     *
     * @return array<string, mixed>|null Возвращает данные пользователя или null, если пользователь не найден.
     */
    public function find(int $id): ?array {
        $statement = $this->pdo->prepare(
            'SELECT profiles.*, chats.title AS chat_title
            FROM profiles
            LEFT JOIN chats ON chats.chat_id = profiles.chat_id
            WHERE profiles.id = :id'
        );
        $statement->execute(['id' => $id]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * Находит включенного пользователя по chat_id.
     *
     * @return array<string, mixed>|null
     */
    public function findEnabledByChat(int $chatId): ?array {
        $statement = $this->pdo->prepare(
            'SELECT profiles.*, chats.title AS chat_title
            FROM profiles
            LEFT JOIN chats ON chats.chat_id = profiles.chat_id
            WHERE profiles.chat_id = :chat_id AND profiles.enabled = 1
            ORDER BY profiles.id ASC
            LIMIT 1'
        );
        $statement->execute([
            'chat_id' => $chatId,
        ]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    public function hasUserId(int $userId): bool {
        $statement = $this->pdo->prepare('SELECT 1 FROM profiles WHERE user_id = :user_id LIMIT 1');
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasChatId(int $chatId): bool {
        $statement = $this->pdo->prepare('SELECT 1 FROM profiles WHERE chat_id = :chat_id LIMIT 1');
        $statement->execute(['chat_id' => $chatId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasConflictingChatId(int $chatId, string $chatType, ?int $exceptProfileId = null): bool {
        $query = 'SELECT chat_type FROM profiles WHERE chat_id = :chat_id';
        $params = ['chat_id' => $chatId];

        if ($exceptProfileId !== null) {
            $query .= ' AND id <> :except_profile_id';
            $params['except_profile_id'] = $exceptProfileId;
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);
        $existingTypes = $statement->fetchAll(PDO::FETCH_COLUMN);

        if ($existingTypes === []) {
            return false;
        }

        if (!$this->isGroupChatType($chatType)) {
            return true;
        }

        foreach ($existingTypes as $existingType) {
            if (!$this->isGroupChatType((string) $existingType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Создаёт нового пользователя.
     *
     * @param array<string, mixed> $data Входные данные формы (user_id, username, chat_id и др.).
     */
    public function create(array $data): void {
        $payload = $this->normalize($data);
        $statement = $this->pdo->prepare(
            'INSERT INTO profiles (
                name, active_bot_id, user_id, username, first_name, last_name,
                chat_id, chat_type, language_code, enabled
            ) VALUES (
                :name, :active_bot_id, :user_id, :username, :first_name, :last_name,
                :chat_id, :chat_type, :language_code, :enabled
            )'
        );

        $statement->execute($payload);
        $this->syncChatMembership((int) $this->pdo->lastInsertId(), $payload);
    }

    /**
     * Обновляет существующего пользователя.
     *
     * @param array<string, mixed> $data Входные данные формы.
     */
    public function update(int $id, array $data): void {
        $payload = $this->normalize($data);
        $payload['id'] = $id;

        $statement = $this->pdo->prepare(
            'UPDATE profiles SET
                name = :name,
                active_bot_id = :active_bot_id,
                user_id = :user_id,
                username = :username,
                first_name = :first_name,
                last_name = :last_name,
                chat_id = :chat_id,
                chat_type = :chat_type,
                language_code = :language_code,
                enabled = :enabled,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id'
        );

        $statement->execute($payload);
        $this->syncChatMembership($id, $payload);
    }

    /**
     * Удаляет пользователя по идентификатору.
     */
    public function delete(int $id): void {
        $deleteMembership = $this->pdo->prepare('DELETE FROM chat_members WHERE profile_id = :id');
        $deleteMembership->execute(['id' => $id]);

        $statement = $this->pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Нормализует входные данные пользователя перед записью в БД.
     *
     * Удаляет пробелы, приводит типы, задаёт значения по умолчанию.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array {
        $username = ltrim(trim((string) ($data['username'] ?? '')), '@');
        $lastName = trim((string) ($data['last_name'] ?? ''));

        return [
            'name' => $username,
            'active_bot_id' => null,
            'user_id' => (int) ($data['user_id'] ?? 0),
            'username' => $username,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => $lastName === '' ? null : $lastName,
            'chat_id' => (int) ($data['chat_id'] ?? 0),
            'chat_type' => in_array($data['chat_type'] ?? '', ['private', 'group', 'supergroup', 'channel'], true)
                ? $data['chat_type']
                : 'private',
            'language_code' => trim((string) ($data['language_code'] ?? 'ru')) ?: 'ru',
            'enabled' => isset($data['enabled']) ? 1 : 0,
        ];
    }

    private function isGroupChatType(string $chatType): bool {
        return in_array($chatType, ['group', 'supergroup'], true);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function syncChatMembership(int $profileId, array $profile): void {
        $chatId = (int) $profile['chat_id'];
        $chatType = (string) $profile['chat_type'];
        $title = $this->chatTitle($profile);

        $upsertChat = $this->pdo->prepare(
            'INSERT INTO chats (chat_id, type, title)
            VALUES (:chat_id, :type, :title)
            ON CONFLICT(chat_id) DO UPDATE SET
                type = excluded.type,
                title = COALESCE(NULLIF(chats.title, \'\'), excluded.title),
                updated_at = CURRENT_TIMESTAMP'
        );
        $upsertChat->execute([
            'chat_id' => $chatId,
            'type' => $chatType,
            'title' => $title,
        ]);

        $chatRowId = $this->chatRowId($chatId);
        if ($chatRowId === null) {
            return;
        }

        $deleteOldMemberships = $this->pdo->prepare(
            'DELETE FROM chat_members WHERE profile_id = :profile_id AND chat_row_id <> :chat_row_id'
        );
        $deleteOldMemberships->execute([
            'profile_id' => $profileId,
            'chat_row_id' => $chatRowId,
        ]);

        $upsertMember = $this->pdo->prepare(
            'INSERT INTO chat_members (chat_row_id, profile_id, role)
            VALUES (:chat_row_id, :profile_id, \'member\')
            ON CONFLICT(chat_row_id, profile_id) DO NOTHING'
        );
        $upsertMember->execute([
            'chat_row_id' => $chatRowId,
            'profile_id' => $profileId,
        ]);
    }

    private function chatRowId(int $chatId): ?int {
        $statement = $this->pdo->prepare('SELECT id FROM chats WHERE chat_id = :chat_id');
        $statement->execute(['chat_id' => $chatId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function chatTitle(array $profile): string {
        $chatType = (string) $profile['chat_type'];
        if ($this->isGroupChatType($chatType) || $chatType === 'channel') {
            return 'Chat ' . (string) $profile['chat_id'];
        }

        return trim((string) $profile['first_name'] . ' ' . (string) ($profile['last_name'] ?? ''));
    }
}

<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с профилями в SQLite.
 *
 * Предоставляет CRUD-операции для сущности Profile:
 * создание, чтение (всех c JOIN ботов и по id), обновление и удаление.
 */
final readonly class ProfileRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Возвращает список всех профилей с информацией о привязанном боте,
     * отсортированный по дате создания (новые сверху).
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array {
        $statement = $this->pdo->query(
            'SELECT profiles.*, bots.display_name AS bot_display_name, bots.username AS bot_username
            FROM profiles
            LEFT JOIN bots ON bots.id = profiles.active_bot_id
            ORDER BY profiles.created_at DESC, profiles.id DESC'
        );

        return $statement->fetchAll();
    }

    /**
     * Находит профиль по его внутреннему идентификатору.
     *
     * @return array<string, mixed>|null Возвращает данные профиля или null, если профиль не найден.
     */
    public function find(int $id): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM profiles WHERE id = :id');
        $statement->execute(['id' => $id]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * Находит активный профиль, чей диалог соответствует боту и chat_id.
     *
     * @return array<string, mixed>|null
     */
    public function findEnabledByBotAndChat(int $botId, int $chatId): ?array {
        $statement = $this->pdo->prepare(
            'SELECT * FROM profiles
            WHERE active_bot_id = :bot_id AND chat_id = :chat_id AND enabled = 1
            ORDER BY id ASC
            LIMIT 1'
        );
        $statement->execute([
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);
        $profile = $statement->fetch();

        return $profile === false ? null : $profile;
    }

    /**
     * Создаёт новый профиль.
     *
     * @param array<string, mixed> $data Входные данные формы (name, user_id, username, chat_id и др.).
     */
    public function create(array $data): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO profiles (
                name, active_bot_id, user_id, username, first_name, last_name,
                chat_id, chat_type, language_code, enabled
            ) VALUES (
                :name, :active_bot_id, :user_id, :username, :first_name, :last_name,
                :chat_id, :chat_type, :language_code, :enabled
            )'
        );

        $statement->execute($this->normalize($data));
    }

    /**
     * Обновляет существующий профиль.
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
    }

    /**
     * Удаляет профиль по идентификатору.
     */
    public function delete(int $id): void {
        $statement = $this->pdo->prepare('DELETE FROM profiles WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Нормализует входные данные профиля перед записью в БД.
     *
     * Удаляет пробелы, приводит типы, задаёт значения по умолчанию.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array {
        $activeBotId = trim((string) ($data['active_bot_id'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'active_bot_id' => $activeBotId === '' ? null : (int) $activeBotId,
            'user_id' => (int) ($data['user_id'] ?? 0),
            'username' => ltrim(trim((string) ($data['username'] ?? '')), '@'),
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
}

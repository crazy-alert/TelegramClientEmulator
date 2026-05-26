<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий для работы с ботами в SQLite.
 *
 * Предоставляет CRUD-операции для сущности Bot:
 * создание, чтение (всех и по id), обновление и удаление.
 */
final readonly class BotRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * Возвращает список всех ботов, отсортированный по дате создания (новые сверху).
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array {
        $statement = $this->pdo->query('SELECT * FROM bots ORDER BY created_at DESC, id DESC');

        return $statement->fetchAll();
    }

    /**
     * Находит бота по его внутреннему идентификатору.
     *
     * @return array<string, mixed>|null Возвращает данные бота или null, если бот не найден.
     */
    public function find(int $id): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM bots WHERE id = :id');
        $statement->execute(['id' => $id]);
        $bot = $statement->fetch();

        return $bot === false ? null : $bot;
    }

    /**
     * Создаёт нового бота.
     *
     * @param array<string, mixed> $data Входные данные формы (token, username, display_name, delivery_mode и др.).
     */
    public function create(array $data): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO bots (
                token, bot_id, username, display_name, delivery_mode,
                webhook_url, webhook_secret_token, enabled
            ) VALUES (
                :token, :bot_id, :username, :display_name, :delivery_mode,
                :webhook_url, :webhook_secret_token, :enabled
            )'
        );

        $statement->execute($this->normalize($data));
    }

    /**
     * Обновляет существующего бота.
     *
     * @param array<string, mixed> $data Входные данные формы.
     */
    public function update(int $id, array $data): void {
        $payload = $this->normalize($data);
        $payload['id'] = $id;

        $statement = $this->pdo->prepare(
            'UPDATE bots SET
                token = :token,
                bot_id = :bot_id,
                username = :username,
                display_name = :display_name,
                delivery_mode = :delivery_mode,
                webhook_url = :webhook_url,
                webhook_secret_token = :webhook_secret_token,
                enabled = :enabled,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id'
        );

        $statement->execute($payload);
    }

    /**
     * Удаляет бота по идентификатору.
     */
    public function delete(int $id): void {
        $statement = $this->pdo->prepare('DELETE FROM bots WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * Находит бота по токену (используется для маршрутов /bot{token}/...).
     *
     * @return array<string, mixed>|null
     */
    public function findByToken(string $token): ?array {
        $statement = $this->pdo->prepare('SELECT * FROM bots WHERE token = :token');
        $statement->execute(['token' => $token]);
        $bot = $statement->fetch();

        return $bot === false ? null : $bot;
    }

    public function setWebhook(int $id, ?string $webhookUrl, ?string $secretToken): void {
        $deliveryMode = $webhookUrl === null ? 'long_polling' : 'webhook';

        $statement = $this->pdo->prepare(
            'UPDATE bots SET
                delivery_mode = :delivery_mode,
                webhook_url = :webhook_url,
                webhook_secret_token = :webhook_secret_token,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'delivery_mode' => $deliveryMode,
            'webhook_url' => $webhookUrl,
            'webhook_secret_token' => $secretToken,
        ]);
    }

    /**
     * Нормализует входные данные бота перед записью в БД.
     *
     * Удаляет пробелы, приводит типы, задаёт значения по умолчанию.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array {
        $botId = trim((string) ($data['bot_id'] ?? ''));
        $webhookUrl = trim((string) ($data['webhook_url'] ?? ''));
        $webhookSecretToken = trim((string) ($data['webhook_secret_token'] ?? ''));

        return [
            'token' => trim((string) ($data['token'] ?? '')),
            'bot_id' => $botId === '' ? null : (int) $botId,
            'username' => ltrim(trim((string) ($data['username'] ?? '')), '@'),
            'display_name' => trim((string) ($data['display_name'] ?? '')),
            'delivery_mode' => in_array($data['delivery_mode'] ?? '', ['webhook', 'long_polling'], true)
                ? $data['delivery_mode']
                : 'long_polling',
            'webhook_url' => $webhookUrl === '' ? null : $webhookUrl,
            'webhook_secret_token' => $webhookSecretToken === '' ? null : $webhookSecretToken,
            'enabled' => isset($data['enabled']) ? 1 : 0,
        ];
    }
}

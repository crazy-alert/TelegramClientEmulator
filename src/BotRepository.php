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
     * Генерирует пару bot_id/token для формы создания бота.
     *
     * @return array{bot_id: int, token: string}
     */
    public function generateCredentials(): array {
        return $this->generateUniqueCredentials();
    }

    /**
     * @return array{bot_id: int, token: string}
     */
    private function generateUniqueCredentials(?int $preferredBotId = null): array {
        if ($preferredBotId !== null && $this->existsByBotId($preferredBotId)) {
            $preferredBotId = null;
        }

        do {
            $botId = $preferredBotId ?? random_int(10_000, 9_999_999_999);
            $token = $this->generateToken($botId);
        } while ($this->existsByToken($token) || $this->existsByBotId($botId));

        return [
            'bot_id' => $botId,
            'token' => $token,
        ];
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
        $token = trim((string) ($data['token'] ?? ''));
        $generatedToken = trim((string) ($data['generated_token'] ?? ''));
        $webhookUrl = trim((string) ($data['webhook_url'] ?? ''));
        $webhookSecretToken = trim((string) ($data['webhook_secret_token'] ?? ''));
        $generatedCredentials = null;

        if ($token === '') {
            $preferredBotId = preg_match('/^\d{5,10}$/', $botId) === 1 ? (int) $botId : null;
            $generatedCredentials = $this->usableGeneratedCredentials($generatedToken, $preferredBotId)
                ?? $this->generateUniqueCredentials($preferredBotId);
            $token = $generatedCredentials['token'];
        }

        $botIdFromToken = $this->botIdFromToken($token);
        if ($botIdFromToken !== null) {
            $botId = (string) $botIdFromToken;
        } elseif ($botId === '' && $generatedCredentials !== null) {
            $botId = (string) $generatedCredentials['bot_id'];
        }

        return [
            'token' => $token,
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

    private function botIdFromToken(string $token): ?int {
        if (preg_match('/^(\d{5,10}):[a-zA-Z0-9_.+-]{15,}$/', $token, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function generateToken(int $botId): string {
        return $botId . ':' . $this->randomTokenSuffix(32);
    }

    /**
     * @return array{bot_id: int, token: string}|null
     */
    private function usableGeneratedCredentials(string $token, ?int $preferredBotId): ?array {
        if ($preferredBotId !== null || $token === '') {
            return null;
        }

        $botId = $this->botIdFromToken($token);
        if ($botId === null || $this->existsByToken($token) || $this->existsByBotId($botId)) {
            return null;
        }

        return [
            'bot_id' => $botId,
            'token' => $token,
        ];
    }

    private function randomTokenSuffix(int $length): string {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_.+-';
        $suffix = '';
        $maxIndex = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; $i++) {
            $suffix .= $alphabet[random_int(0, $maxIndex)];
        }

        return $suffix;
    }

    private function existsByToken(string $token): bool {
        $statement = $this->pdo->prepare('SELECT 1 FROM bots WHERE token = :token LIMIT 1');
        $statement->execute(['token' => $token]);

        return $statement->fetchColumn() !== false;
    }

    private function existsByBotId(int $botId): bool {
        $statement = $this->pdo->prepare('SELECT 1 FROM bots WHERE bot_id = :bot_id LIMIT 1');
        $statement->execute(['bot_id' => $botId]);

        return $statement->fetchColumn() !== false;
    }
}

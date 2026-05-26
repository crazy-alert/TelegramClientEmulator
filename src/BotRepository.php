<?php

declare(strict_types=1);

namespace App;

use PDO;

final readonly class BotRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->pdo->query('SELECT * FROM bots ORDER BY created_at DESC, id DESC');

        return $statement->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM bots WHERE id = :id');
        $statement->execute(['id' => $id]);
        $bot = $statement->fetch();

        return $bot === false ? null : $bot;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): void
    {
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
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
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

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM bots WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
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


<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * Репозиторий команд бота для методов Bot API `setMyCommands` и `getMyCommands`.
 */
final readonly class BotCommandRepository {

    public function __construct(private PDO $pdo) {
    }

    /**
     * @param list<array{command: string, description: string}> $commands
     * @param array<string, mixed> $scope
     */
    public function replaceForBot(int $botId, array $commands, array $scope = ['type' => 'default'], string $languageCode = ''): void {
        $scopeKey = $this->scopeKey($scope);
        $scopeJson = $this->scopeJson($scope);
        $scopeType = (string) $scope['type'];
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM bot_commands
                WHERE bot_id = :bot_id AND scope_key = :scope_key AND language_code = :language_code'
            );
            $delete->execute([
                'bot_id' => $botId,
                'scope_key' => $scopeKey,
                'language_code' => $languageCode,
            ]);

            $insert = $this->pdo->prepare(
                'INSERT INTO bot_commands (
                    bot_id, scope_type, scope_key, scope_json, language_code, command, description, position
                ) VALUES (
                    :bot_id, :scope_type, :scope_key, :scope_json, :language_code, :command, :description, :position
                )'
            );

            foreach ($commands as $position => $command) {
                $insert->execute([
                    'bot_id' => $botId,
                    'scope_type' => $scopeType,
                    'scope_key' => $scopeKey,
                    'scope_json' => $scopeJson,
                    'language_code' => $languageCode,
                    'command' => $command['command'],
                    'description' => $command['description'],
                    'position' => $position,
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $scope
     */
    public function deleteForBot(int $botId, array $scope = ['type' => 'default'], string $languageCode = ''): void {
        $statement = $this->pdo->prepare(
            'DELETE FROM bot_commands
            WHERE bot_id = :bot_id AND scope_key = :scope_key AND language_code = :language_code'
        );
        $statement->execute([
            'bot_id' => $botId,
            'scope_key' => $this->scopeKey($scope),
            'language_code' => $languageCode,
        ]);
    }

    /**
     * @return list<array{command: string, description: string}>
     */
    public function allForBot(int $botId, array $scope = ['type' => 'default'], string $languageCode = ''): array {
        $statement = $this->pdo->prepare(
            'SELECT command, description
            FROM bot_commands
            WHERE bot_id = :bot_id AND scope_key = :scope_key AND language_code = :language_code
            ORDER BY position ASC, id ASC'
        );
        $statement->execute([
            'bot_id' => $botId,
            'scope_key' => $this->scopeKey($scope),
            'language_code' => $languageCode,
        ]);

        return $this->commandRows($statement->fetchAll());
    }

    /**
     * @return list<array{command: string, description: string}>
     */
    public function forChatContext(int $botId, int|string $chatId, string $chatType, int $userId, string $languageCode): array {
        foreach ($this->scopeCandidates($chatId, $chatType, $userId) as $scope) {
            foreach ([$languageCode, ''] as $candidateLanguage) {
                $commands = $this->allForBot($botId, $scope, $candidateLanguage);
                if ($commands !== []) {
                    return $commands;
                }
            }
        }

        return [];
    }

    /**
     * @return list<array{bot_token: string, scope: array<string, mixed>, language_code: string, commands: list<array{command: string, description: string}>}>
     */
    public function allWithBotTokens(): array {
        $statement = $this->pdo->query(
            'SELECT
                bots.token AS bot_token,
                bot_commands.scope_json,
                bot_commands.language_code,
                bot_commands.command,
                bot_commands.description
            FROM bot_commands
            INNER JOIN bots ON bots.id = bot_commands.bot_id
            ORDER BY bots.id ASC, bot_commands.scope_key ASC, bot_commands.language_code ASC, bot_commands.position ASC, bot_commands.id ASC'
        );

        $groups = [];
        foreach ($statement->fetchAll() as $row) {
            $token = (string) $row['bot_token'];
            $scopeJson = (string) $row['scope_json'];
            $languageCode = (string) $row['language_code'];
            $groupKey = $token . "\n" . $scopeJson . "\n" . $languageCode;
            $scope = json_decode($scopeJson, true);
            $groups[$groupKey] ??= [
                'bot_token' => $token,
                'scope' => is_array($scope) ? $scope : ['type' => 'default'],
                'language_code' => $languageCode,
                'commands' => [],
            ];
            $groups[$groupKey]['commands'][] = [
                'command' => (string) $row['command'],
                'description' => (string) $row['description'],
            ];
        }

        return array_values($groups);
    }

    /**
     * @param array<string, mixed> $scope
     */
    public function scopeKey(array $scope): string {
        return match ((string) ($scope['type'] ?? 'default')) {
            'all_private_chats',
            'all_group_chats',
            'all_chat_administrators' => (string) $scope['type'],
            'chat',
            'chat_administrators' => (string) $scope['type'] . ':' . (string) $scope['chat_id'],
            'chat_member' => 'chat_member:' . (string) $scope['chat_id'] . ':' . (string) $scope['user_id'],
            default => 'default',
        };
    }

    /**
     * @param array<string, mixed> $scope
     */
    private function scopeJson(array $scope): string {
        $encoded = json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{command: string, description: string}>
     */
    private function commandRows(array $rows): array {
        return array_map(
            static fn(array $row): array => [
                'command' => (string) $row['command'],
                'description' => (string) $row['description'],
            ],
            $rows,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function scopeCandidates(int|string $chatId, string $chatType, int $userId): array {
        $scopes = [
            [
                'type' => 'chat_member',
                'chat_id' => $chatId,
                'user_id' => $userId,
            ],
            [
                'type' => 'chat',
                'chat_id' => $chatId,
            ],
        ];

        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $scopes[] = ['type' => 'all_group_chats'];
        } else {
            $scopes[] = ['type' => 'all_private_chats'];
        }

        $scopes[] = ['type' => 'default'];

        return $scopes;
    }
}

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
     */
    public function replaceForBot(int $botId, array $commands): void {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM bot_commands WHERE bot_id = :bot_id');
            $delete->execute(['bot_id' => $botId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO bot_commands (bot_id, command, description, position)
                VALUES (:bot_id, :command, :description, :position)'
            );

            foreach ($commands as $position => $command) {
                $insert->execute([
                    'bot_id' => $botId,
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

    public function deleteForBot(int $botId): void {
        $statement = $this->pdo->prepare('DELETE FROM bot_commands WHERE bot_id = :bot_id');
        $statement->execute(['bot_id' => $botId]);
    }

    /**
     * @return list<array{command: string, description: string}>
     */
    public function allForBot(int $botId): array {
        $statement = $this->pdo->prepare(
            'SELECT command, description
            FROM bot_commands
            WHERE bot_id = :bot_id
            ORDER BY position ASC, id ASC'
        );
        $statement->execute(['bot_id' => $botId]);

        return array_map(
            static fn(array $row): array => [
                'command' => (string) $row['command'],
                'description' => (string) $row['description'],
            ],
            $statement->fetchAll(),
        );
    }

    /**
     * @return list<array{bot_token: string, commands: list<array{command: string, description: string}>}>
     */
    public function allWithBotTokens(): array {
        $statement = $this->pdo->query(
            'SELECT
                bots.token AS bot_token,
                bot_commands.command,
                bot_commands.description
            FROM bot_commands
            INNER JOIN bots ON bots.id = bot_commands.bot_id
            ORDER BY bots.id ASC, bot_commands.position ASC, bot_commands.id ASC'
        );

        $groups = [];
        foreach ($statement->fetchAll() as $row) {
            $token = (string) $row['bot_token'];
            $groups[$token] ??= [
                'bot_token' => $token,
                'commands' => [],
            ];
            $groups[$token]['commands'][] = [
                'command' => (string) $row['command'],
                'description' => (string) $row['description'],
            ];
        }

        return array_values($groups);
    }
}

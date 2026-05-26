<?php

declare(strict_types=1);

namespace App;

use PDO;

final readonly class MigrationRunner
{
    public function __construct(
        private PDO $pdo,
        private string $migrationsPath,
    ) {
    }

    public function run(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version TEXT PRIMARY KEY,
                applied_at TEXT NOT NULL
            )'
        );

        $files = glob($this->migrationsPath . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $version = basename($file);

            if ($this->isApplied($version)) {
                continue;
            }

            $sql = file_get_contents($file);

            if ($sql === false) {
                throw new \RuntimeException('Не удалось прочитать миграцию: ' . $file);
            }

            $this->pdo->beginTransaction();

            try {
                $this->pdo->exec($sql);
                $statement = $this->pdo->prepare(
                    'INSERT INTO schema_migrations (version, applied_at) VALUES (:version, :applied_at)'
                );
                $statement->execute([
                    'version' => $version,
                    'applied_at' => gmdate('c'),
                ]);
                $this->pdo->commit();
            } catch (\Throwable $exception) {
                $this->pdo->rollBack();
                throw $exception;
            }
        }
    }

    private function isApplied(string $version): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = :version');
        $statement->execute(['version' => $version]);

        return (bool) $statement->fetchColumn();
    }
}


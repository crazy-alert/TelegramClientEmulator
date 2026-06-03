<?php

declare(strict_types=1);

namespace App;

use PDO;

final readonly class SettingsRepository {

    public function __construct(private PDO $pdo) {
    }

    public function get(string $key): ?string {
        $statement = $this->pdo->prepare('SELECT value FROM settings WHERE key = :key');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    public function set(string $key, string $value): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (key, value, updated_at)
             VALUES (:key, :value, CURRENT_TIMESTAMP)
             ON CONFLICT(key) DO UPDATE SET
                 value = excluded.value,
                 updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }
}

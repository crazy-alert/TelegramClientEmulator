<?php

declare(strict_types=1);

namespace App;

/**
 * Чистые helpers для чтения и нормализации параметров Telegram Bot API.
 */
final readonly class BotApiParams {

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public static function all(array $query, array $body): array {
        return array_replace($query, $body);
    }

    public static function int(mixed $value, int $default): int {
        if (is_int($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return $default;
        }

        return (int) $value;
    }

    public static function float(mixed $value): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    public static function truthy(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return list<string>|null
     */
    public static function allowedUpdates(mixed $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value) || $value === []) {
            return null;
        }

        $allowedUpdates = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $allowedUpdates[] = $item;
            }
        }

        return $allowedUpdates === [] ? null : $allowedUpdates;
    }

    /**
     * @return list<array{command: string, description: string}>|null
     */
    public static function commands(mixed $value): ?array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $commands = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                return null;
            }

            $command = ltrim(trim((string) ($item['command'] ?? '')), '/');
            $description = trim((string) ($item['description'] ?? ''));

            if (preg_match('/^[a-z0-9_]{1,32}$/', $command) !== 1 || $description === '' || mb_strlen($description) > 256) {
                return null;
            }

            $commands[] = [
                'command' => $command,
                'description' => $description,
            ];
        }

        return count($commands) > 100 ? null : $commands;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function commandScope(mixed $value): ?array {
        if ($value === null || $value === '') {
            return ['type' => 'default'];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        $type = (string) ($value['type'] ?? '');
        if (!in_array($type, [
            'default',
            'all_private_chats',
            'all_group_chats',
            'all_chat_administrators',
            'chat',
            'chat_administrators',
            'chat_member',
        ], true)) {
            return null;
        }

        if (in_array($type, ['default', 'all_private_chats', 'all_group_chats', 'all_chat_administrators'], true)) {
            return ['type' => $type];
        }

        $chatId = self::scopeScalar($value['chat_id'] ?? null);
        if ($chatId === null) {
            return null;
        }

        if ($type !== 'chat_member') {
            return [
                'type' => $type,
                'chat_id' => $chatId,
            ];
        }

        $userId = self::scopeScalar($value['user_id'] ?? null);
        if ($userId === null) {
            return null;
        }

        return [
            'type' => $type,
            'chat_id' => $chatId,
            'user_id' => $userId,
        ];
    }

    public static function languageCode(mixed $value): string {
        if (!is_scalar($value)) {
            return '';
        }

        return substr(trim((string) $value), 0, 64);
    }

    private static function scopeScalar(mixed $value): int|string|null {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }

            if (preg_match('/^-?\d+$/', $value) === 1) {
                return (int) $value;
            }

            return $value;
        }

        return null;
    }

    /**
     * @return list<array{text: string, voter_count: int}>|null
     */
    public static function pollOptions(mixed $value): ?array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value) || count($value) < 2 || count($value) > 10) {
            return null;
        }

        $options = [];
        foreach ($value as $item) {
            $text = is_array($item)
                ? trim((string) ($item['text'] ?? ''))
                : trim((string) $item);
            if ($text === '' || mb_strlen($text) > 100) {
                return null;
            }
            $options[] = [
                'text' => $text,
                'voter_count' => 0,
            ];
        }

        return $options;
    }
}

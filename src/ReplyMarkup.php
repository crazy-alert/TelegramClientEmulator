<?php

declare(strict_types=1);

namespace App;

/**
 * Единая работа с Bot API reply_markup внутри messages.raw_payload.
 */
final readonly class ReplyMarkup {

    /**
     * @return array<string, mixed>|null
     */
    public static function fromBotApiParam(mixed $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $allowedKeys = [
            'inline_keyboard',
            'keyboard',
            'resize_keyboard',
            'one_time_keyboard',
            'is_persistent',
            'input_field_placeholder',
            'selective',
            'remove_keyboard',
            'force_reply',
        ];

        return array_intersect_key($value, array_flip($allowedKeys));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function withReplyMarkup(array $payload, ?array $replyMarkup): array {
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function fromMessage(array $message): ?array {
        $rawPayload = json_decode((string) ($message['raw_payload'] ?? ''), true);

        return is_array($rawPayload) ? self::fromPayload($rawPayload) : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public static function fromPayload(array $payload): ?array {
        return isset($payload['reply_markup']) && is_array($payload['reply_markup'])
            ? $payload['reply_markup']
            : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function encodePayload(array $payload): string {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return string|null
     */
    public static function encodeOnly(?array $replyMarkup): ?string {
        return $replyMarkup === null
            ? null
            : self::encodePayload(['reply_markup' => $replyMarkup]);
    }

    /**
     * Возвращает актуальную reply keyboard из истории сообщений бота.
     *
     * @param list<array<string, mixed>> $messages
     * @return array<mixed>|null
     */
    public static function latestKeyboard(array $messages): ?array {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            if (($messages[$i]['direction'] ?? '') !== 'bot') {
                continue;
            }

            $markup = self::fromMessage($messages[$i]);

            if ($markup !== null && !empty($markup['remove_keyboard'])) {
                return null;
            }

            if ($markup !== null && isset($markup['keyboard']) && is_array($markup['keyboard'])) {
                return $markup['keyboard'];
            }
        }

        return null;
    }
}

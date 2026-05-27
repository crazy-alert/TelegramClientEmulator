<?php

declare(strict_types=1);

namespace App;

/**
 * Генератор Telegram-совместимых Update payload'ов.
 *
 * Принимает данные сообщения, пользователя и бота, возвращает JSON-подобный массив,
 * соответствующий формату Telegram Bot API Update.
 *
 * Расширяемость: для новых типов updates (callback_query, edited_message)
 * достаточно добавить новые публичные методы без изменения существующих.
 */
final readonly class UpdateGenerator {

    /**
     * Генерирует Update для текстового сообщения от пользователя.
     *
     * @param array<string, mixed> $message  Запись из таблицы messages.
     * @param array<string, mixed> $profile  Запись пользователя из таблицы profiles.
     * @param array<string, mixed> $bot      Запись из таблицы bots.
     * @return array<string, mixed>          Telegram-like Update payload.
     */
    public function generate(array $message, array $profile, array $bot): array {
        $text = (string) ($message['text'] ?? '');
        $chatId = (int) ($profile['chat_id'] ?? 0);
        $userId = (int) ($profile['user_id'] ?? 0);
        $timestamp = time();

        $messagePayload = [
            'message_id' => (int) ($message['telegram_message_id'] ?? 0),
            'date' => $timestamp,
            'chat' => [
                'id' => $chatId,
                'type' => $profile['chat_type'] ?? 'private',
                'username' => $profile['username'] ?? '',
                'first_name' => $profile['first_name'] ?? '',
                'last_name' => $profile['last_name'] ?? '',
            ],
            'from' => [
                'id' => $userId,
                'is_bot' => false,
                'username' => $profile['username'] ?? '',
                'first_name' => $profile['first_name'] ?? '',
                'last_name' => $profile['last_name'] ?? '',
                'language_code' => $profile['language_code'] ?? 'ru',
            ],
            'text' => $text,
        ];

        // Если сообщение начинается с '/', добавляем entity bot_command
        $entities = $this->extractEntities($text);
        if ($entities !== []) {
            $messagePayload['entities'] = $entities;
        }

        return [
            'update_id' => 0, // Будет заполнено в UpdateRepository после сохранения
            'message' => $messagePayload,
        ];
    }

    /**
     * Извлекает сущности (entities) из текста сообщения.
     *
     * Поддерживает bot_command (текст, начинающийся с '/').
     * В будущем может быть расширен для mention, hashtag, url и др.
     *
     * @return list<array{offset: int, length: int, type: string}>
     */
    private function extractEntities(string $text): array {
        $entities = [];

        // bot_command: строка, начинающаяся с '/' и содержащая только буквы, цифры и подчёркивания
        if (preg_match('#^(/\w+)#u', $text, $matches) === 1) {
            $entities[] = [
                'offset' => 0,
                'length' => mb_strlen($matches[1]),
                'type' => 'bot_command',
            ];
        }

        return $entities;
    }
}

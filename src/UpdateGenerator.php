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
            'chat' => $this->chatPayload($profile),
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
     * Генерирует Update для нажатия inline-кнопки с callback_data.
     *
     * @param array<string, mixed> $message  Сообщение бота, к которому привязана кнопка.
     * @param array<string, mixed> $profile  Пользователь, который нажал кнопку.
     * @param array<string, mixed> $bot      Бот, отправивший сообщение.
     * @return array<string, mixed>
     */
    public function generateCallbackQuery(
        array $message,
        array $profile,
        array $bot,
        string $callbackData,
    ): array {
        $timestamp = strtotime((string) ($message['created_at'] ?? '')) ?: time();
        $messagePayload = [
            'message_id' => (int) ($message['telegram_message_id'] ?? 0),
            'date' => $timestamp,
            'chat' => $this->chatPayload($profile),
            'from' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'] ?? '',
                'username' => $bot['username'] ?? '',
            ],
            'text' => (string) ($message['text'] ?? ''),
        ];

        $replyMarkup = ReplyMarkup::fromMessage($message);
        if ($replyMarkup !== null) {
            $messagePayload['reply_markup'] = $replyMarkup;
        }

        return [
            'update_id' => 0,
            'callback_query' => [
                'id' => hash('sha256', implode(':', [
                    (string) ($message['id'] ?? 0),
                    (string) ($profile['id'] ?? 0),
                    $callbackData,
                    (string) microtime(true),
                ])),
                'from' => [
                    'id' => (int) ($profile['user_id'] ?? 0),
                    'is_bot' => false,
                    'username' => $profile['username'] ?? '',
                    'first_name' => $profile['first_name'] ?? '',
                    'last_name' => $profile['last_name'] ?? '',
                    'language_code' => $profile['language_code'] ?? 'ru',
                ],
                'message' => $messagePayload,
                'chat_instance' => hash('sha256', (string) ($profile['chat_id'] ?? '0')),
                'data' => $callbackData,
            ],
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

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function chatPayload(array $profile): array {
        $chatType = (string) ($profile['chat_type'] ?? 'private');
        $payload = [
            'id' => (int) ($profile['chat_id'] ?? 0),
            'type' => $chatType,
        ];

        if (in_array($chatType, ['group', 'supergroup', 'channel'], true)) {
            $payload['title'] = 'Chat ' . (string) ($profile['chat_id'] ?? '0');

            return $payload;
        }

        $payload['username'] = $profile['username'] ?? '';
        $payload['first_name'] = $profile['first_name'] ?? '';
        $payload['last_name'] = $profile['last_name'] ?? '';

        return $payload;
    }
}

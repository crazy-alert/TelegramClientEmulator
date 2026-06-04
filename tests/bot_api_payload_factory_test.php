<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/BotApiParams.php';
require dirname(__DIR__) . '/src/ReplyMarkup.php';
require dirname(__DIR__) . '/src/BotApiPayloadFactory.php';

use App\BotApiPayloadFactory;
use App\ReplyMarkup;

final class BotApiPayloadFactoryTestFailure extends RuntimeException {
}

function assertBotApiPayloadSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new BotApiPayloadFactoryTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

$factory = new BotApiPayloadFactory();

$bot = [
    'bot_id' => 123456,
    'display_name' => 'Local Bot',
    'username' => 'local_bot',
];
$profile = [
    'chat_id' => 42,
    'chat_type' => 'private',
    'username' => 'tester',
    'first_name' => 'Test',
    'last_name' => 'User',
];
$replyMarkup = [
    'inline_keyboard' => [
        [
            ['text' => 'Action', 'callback_data' => 'action'],
        ],
    ],
];

assertBotApiPayloadSame(
    [
        'message_id' => 7,
        'from' => [
            'id' => 123456,
            'is_bot' => true,
            'first_name' => 'Local Bot',
            'username' => 'local_bot',
        ],
        'chat' => [
            'id' => 42,
            'type' => 'private',
            'username' => 'tester',
            'first_name' => 'Test',
            'last_name' => 'User',
        ],
        'date' => 1700000000,
        'text' => 'Hello',
        'reply_markup' => $replyMarkup,
    ],
    $factory->message([
        'telegram_message_id' => 7,
        'created_at' => '@1700000000',
        'text' => 'Hello',
        'raw_payload' => ReplyMarkup::encodeOnly($replyMarkup),
    ], $profile, $bot),
    'message должен собирать Bot API Message с from, chat, text и reply_markup',
);

$photo = [
    [
        'file_id' => 'photo-file-id',
        'file_unique_id' => 'photo-unique',
        'width' => 0,
        'height' => 0,
    ],
];
assertBotApiPayloadSame(
    [
        'message_id' => 8,
        'from' => [
            'id' => 123456,
            'is_bot' => true,
            'first_name' => 'Local Bot',
            'username' => 'local_bot',
        ],
        'chat' => [
            'id' => 42,
            'type' => 'private',
            'username' => 'tester',
            'first_name' => 'Test',
            'last_name' => 'User',
        ],
        'date' => 1700000000,
        'photo' => $photo,
        'caption' => 'Caption',
    ],
    $factory->message([
        'telegram_message_id' => 8,
        'created_at' => '@1700000000',
        'text' => 'Caption',
        'raw_payload' => json_encode([
            'photo' => $photo,
            'caption' => 'Caption',
        ], JSON_THROW_ON_ERROR),
    ], $profile, $bot),
    'message должен поднимать photo и caption из raw_payload',
);

assertBotApiPayloadSame(
    [
        'id' => -10042,
        'type' => 'supergroup',
        'title' => 'Chat -10042',
    ],
    $factory->chat([
        'chat_id' => -10042,
        'chat_type' => 'supergroup',
    ]),
    'chat должен добавлять title для group/supergroup/channel',
);

$storedMedia = [
    'file_id' => 'local-media:abc',
    'file_unique_id' => 'unique-abc',
    'file_name' => 'report.pdf',
    'mime_type' => 'application/pdf',
    'file_size' => 1234,
];
assertBotApiPayloadSame(
    [
        [
            'file_id' => 'local-media:abc',
            'file_unique_id' => 'unique-abc',
            'width' => 0,
            'height' => 0,
            'file_size' => 1234,
        ],
    ],
    $factory->photoSizes('local-media:abc', $storedMedia),
    'photoSizes должен сохранять file_size загруженного media',
);
assertBotApiPayloadSame(
    [
        'file_id' => 'local-media:abc',
        'file_unique_id' => 'unique-abc',
        'file_name' => 'report.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1234,
    ],
    $factory->document('local-media:abc', $storedMedia),
    'document должен сохранять metadata загруженного media',
);

assertBotApiPayloadSame(
    [
        'file_id' => 'https://example.test/video.mp4',
        'file_unique_id' => substr(sha1('video:https://example.test/video.mp4'), 0, 16),
        'width' => 640,
        'duration' => 12,
        'mime_type' => 'video/mp4',
    ],
    $factory->typedMedia('video', 'https://example.test/video.mp4', [
        'width' => '640',
        'height' => '',
        'duration' => '12',
        'mime_type' => 'video/mp4',
    ]),
    'typedMedia должен нормализовать optional int/string поля',
);

echo "OK: bot api payload factory tests passed\n";

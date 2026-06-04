<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/ReplyMarkup.php';

use App\ReplyMarkup;

final class ReplyMarkupTestFailure extends RuntimeException {
}

/**
 * @param mixed $actual
 */
function assertReplyMarkupSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new ReplyMarkupTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

$inlineMarkup = [
    'inline_keyboard' => [
        [
            ['text' => 'Action', 'callback_data' => 'action'],
        ],
    ],
];

$replyMarkup = [
    'keyboard' => [
        [
            ['text' => 'Reply A'],
            'Reply B',
        ],
    ],
    'resize_keyboard' => true,
];

assertReplyMarkupSame(
    $inlineMarkup,
    ReplyMarkup::fromBotApiParam(json_encode($inlineMarkup, JSON_THROW_ON_ERROR)),
    'Inline keyboard должен читаться из JSON-строки Bot API',
);

assertReplyMarkupSame(
    $replyMarkup,
    ReplyMarkup::fromBotApiParam($replyMarkup),
    'Reply keyboard должен читаться из массива Bot API',
);

assertReplyMarkupSame(
    null,
    ReplyMarkup::fromBotApiParam('not-json'),
    'Некорректный reply_markup должен отклоняться',
);

$encoded = ReplyMarkup::encodeOnly($inlineMarkup);
assertReplyMarkupSame(
    $inlineMarkup,
    ReplyMarkup::fromMessage(['raw_payload' => $encoded]),
    'Reply markup должен читаться из messages.raw_payload',
);

assertReplyMarkupSame(
    [
        [
            ['text' => 'Reply A'],
            'Reply B',
        ],
    ],
    ReplyMarkup::latestKeyboard([
        ['direction' => 'bot', 'raw_payload' => ReplyMarkup::encodeOnly($replyMarkup)],
    ]),
    'Актуальная reply keyboard должна находиться по истории сообщений',
);

assertReplyMarkupSame(
    null,
    ReplyMarkup::latestKeyboard([
        ['direction' => 'bot', 'raw_payload' => ReplyMarkup::encodeOnly($replyMarkup)],
        ['direction' => 'bot', 'raw_payload' => ReplyMarkup::encodeOnly(['remove_keyboard' => true])],
    ]),
    'remove_keyboard должен скрывать предыдущую reply keyboard',
);

echo "OK: reply markup tests passed\n";

<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiMessageCoreScenarios(array $context): void {
    extract($context);
    $scenario = '[message core] ';
    $inlineMarkup = [
        'inline_keyboard' => [
            [
                [
                    'text' => 'Inline action',
                    'callback_data' => 'inline-action',
                ],
                [
                    'text' => 'Docs',
                    'url' => 'https://example.test/docs',
                ],
            ],
        ],
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', json_encode([
        'chat_id' => 1001,
        'text' => 'Inline buttons',
        'reply_markup' => $inlineMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(4, $json['result']['message_id'], $scenario . 'sendMessage с inline keyboard возвращает следующий message_id');
    assertSameValue($inlineMarkup, $json['result']['reply_markup'], $scenario . 'sendMessage возвращает inline reply_markup');

    $replyMarkup = [
        'keyboard' => [
            [
                [
                    'text' => 'Reply A',
                ],
                [
                    'text' => 'Reply B',
                ],
            ],
        ],
        'resize_keyboard' => true,
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', json_encode([
        'chat_id' => 1001,
        'text' => 'Reply keyboard',
        'reply_markup' => $replyMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(5, $json['result']['message_id'], $scenario . 'sendMessage с reply keyboard возвращает следующий message_id');
    assertSameValue($replyMarkup, $json['result']['reply_markup'], $scenario . 'sendMessage возвращает reply keyboard');

    $editedMarkup = [
        'keyboard' => [
            [
                [
                    'text' => 'Reply A',
                ],
                [
                    'text' => 'Reply C',
                ],
            ],
        ],
        'resize_keyboard' => true,
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', json_encode([
        'chat_id' => 1001,
        'message_id' => 5,
        'text' => 'Edited reply keyboard',
        'reply_markup' => $editedMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(5, $json['result']['message_id'], $scenario . 'editMessageText возвращает исходный message_id');
    assertSameValue('Edited reply keyboard', $json['result']['text'], $scenario . 'editMessageText возвращает обновленный текст');
    assertSameValue($editedMarkup, $json['result']['reply_markup'], $scenario . 'editMessageText возвращает обновленный reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', formBody([
        'chat_id' => '1001',
        'message_id' => '3',
        'text' => 'Cannot edit user message',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: message to edit not found', $json['description'], $scenario . 'editMessageText не редактирует сообщения пользователя');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', formBody([
        'chat_id' => '9999',
        'message_id' => '5',
        'text' => 'No chat',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], $scenario . 'editMessageText проверяет существование чата');
}

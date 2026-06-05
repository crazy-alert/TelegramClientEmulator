<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiCoreScenarios(array $context): void {
    extract($context);
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMe'), 200, true);
    assertSameValue(123456, $json['result']['id'], 'getMe возвращает bot id');
    assertSameValue(true, $json['result']['is_bot'], 'getMe возвращает User.is_bot=true');
    assertSameValue('Local Bot', $json['result']['first_name'], 'getMe возвращает имя бота');
    assertSameValue('local_bot', $json['result']['username'], 'getMe возвращает username бота');
    assertArrayHasKeyValue('can_join_groups', $json['result'], 'getMe возвращает Bot API capability fields');

    $commands = [
        [
            'command' => 'start',
            'description' => 'Начать диалог',
        ],
        [
            'command' => 'help',
            'description' => 'Показать помощь',
        ],
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setMyCommands', json_encode([
        'commands' => $commands,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(true, $json['result'], 'setMyCommands возвращает true');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue($commands, $json['result'], 'getMyCommands возвращает сохраненные команды');

    $privateEnglishCommands = [
        [
            'command' => 'private_en',
            'description' => 'Private English command',
        ],
    ];
    $privateScope = [
        'type' => 'all_private_chats',
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setMyCommands', json_encode([
        'commands' => $privateEnglishCommands,
        'scope' => $privateScope,
        'language_code' => 'en',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(true, $json['result'], 'setMyCommands сохраняет scoped language commands');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getMyCommands', json_encode([
        'scope' => $privateScope,
        'language_code' => 'en',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue($privateEnglishCommands, $json['result'], 'getMyCommands возвращает exact scope/language commands');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue($commands, $json['result'], 'getMyCommands без scope возвращает default commands');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Чат должен открываться для проверки scoped commands');
    assertTrueValue(str_contains($chat['body'], '/private_en'), 'UI должен выбирать scoped language commands для текущего private profile');
    assertTrueValue(!str_contains($chat['body'], '/start —'), 'UI не должен показывать default commands, если найден scoped language набор');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getMyCommands', json_encode([
        'scope' => ['type' => 'chat'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 400, false);
    assertSameValue(400, $json['error_code'], 'getMyCommands валидирует scope');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setMyCommands', formBody([
        'commands' => json_encode([
            [
                'command' => 'InvalidCommand',
                'description' => 'Некорректная команда',
            ],
        ], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue(400, $json['error_code'], 'setMyCommands валидирует формат команд');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot000000:missing-token/getMe'), 404, false);
    assertSameValue(404, $json['error_code'], 'Неизвестный token возвращает Telegram-like 404');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', json_encode([
        'chat_id' => 1001,
        'text' => 'Hello from JSON',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['result']['message_id'], 'sendMessage JSON возвращает первый message_id');
    assertSameValue(123456, $json['result']['from']['id'], 'sendMessage возвращает Message.from для бота');
    assertSameValue(true, $json['result']['from']['is_bot'], 'sendMessage возвращает Message.from.is_bot=true');
    assertSameValue(1001, $json['result']['chat']['id'], 'sendMessage возвращает Chat.id');
    assertSameValue('private', $json['result']['chat']['type'], 'sendMessage возвращает Chat.type');
    assertSameValue('Hello from JSON', $json['result']['text'], 'sendMessage возвращает текст');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '1001',
        'text' => 'Hello from form',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(2, $json['result']['message_id'], 'sendMessage form-urlencoded возвращает следующий message_id');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "text" is required', $json['description'], 'sendMessage требует text');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '9999',
        'text' => 'No chat',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'sendMessage проверяет существование чата');

}

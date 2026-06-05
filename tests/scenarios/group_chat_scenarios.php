<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runGroupChatScenarios(array $context): void {
    extract($context);
    $groupToken = '345678:group-local-token';

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/bots', json_encode([
        'bots' => [
            [
                'token' => $groupToken,
                'bot_id' => 345678,
                'username' => 'group_bot',
                'display_name' => 'Group Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['created'], 'Импорт group bot должен создавать отдельного бота');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/profiles', json_encode([
        'profiles' => [
            [
                'user_id' => 3001,
                'username' => 'group_alice',
                'first_name' => 'Alice',
                'last_name' => 'Sender',
                'chat_id' => -100300,
                'chat_type' => 'group',
                'language_code' => 'ru',
                'enabled' => true,
            ],
            [
                'user_id' => 3002,
                'username' => 'group_bob',
                'first_name' => 'Bob',
                'last_name' => 'Sender',
                'chat_id' => -100300,
                'chat_type' => 'group',
                'language_code' => 'ru',
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(2, $json['created'], 'Импорт group profiles должен разрешать общий chat_id');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '3',
        'bot_id' => '3',
        'text' => 'Group hello from Alice',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение первого отправителя группы должно сохраняться');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '4',
        'bot_id' => '3',
        'text' => 'Group hello from Bob',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение второго отправителя группы должно сохраняться');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=3&bot_id=3');
    assertSameValue(200, $chat['status'], 'Group chat должен открываться');
    assertTrueValue(str_contains($chat['body'], 'Пользователь / отправитель'), 'UI должен явно выбирать отправителя');
    assertTrueValue(str_contains($chat['body'], 'group #-100300'), 'UI должен показывать тип и id group chat');
    assertTrueValue(str_contains($chat['body'], 'Group hello from Alice'), 'Group chat показывает сообщение первого отправителя');
    assertTrueValue(str_contains($chat['body'], 'Group hello from Bob'), 'Group chat показывает общую историю по chat_id');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $groupToken . '/getUpdates', json_encode([
        'limit' => 2,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(2, count($json['result']), 'Group bot должен получить два message update');
    assertSameValue(-100300, $json['result'][0]['message']['chat']['id'], 'Group update содержит общий chat_id');
    assertSameValue('group', $json['result'][0]['message']['chat']['type'], 'Group update содержит chat.type=group');
    assertSameValue('Chat -100300', $json['result'][0]['message']['chat']['title'], 'Group update содержит title чата');
    assertSameValue(3001, $json['result'][0]['message']['from']['id'], 'Первый group update содержит отправителя Alice');
}

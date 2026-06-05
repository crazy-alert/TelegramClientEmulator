<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runLongPollingScenarios(array $context): void {
    extract($context);
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?allowed_updates=' . rawurlencode('["callback_query"]')), 200, true);
    assertSameValue([], $json['result'], 'allowed_updates фильтрует message update');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, count($json['result']), 'getUpdates возвращает pending message update');
    $update = $json['result'][0];
    assertTrueValue(($update['update_id'] ?? 0) >= 100000001, 'Update.update_id должен быть реальным id из очереди');
    assertSameValue('/help', $update['message']['text'], 'Update.message.text должен совпадать с отправленным текстом');
    assertSameValue([
        [
            'offset' => 0,
            'length' => 5,
            'type' => 'bot_command',
        ],
    ], $update['message']['entities'], 'Update.message.entities должен содержать bot_command');

    $nextOffset = ((int) $update['update_id']) + 1;
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);
    assertSameValue([], $json['result'], 'offset подтверждает старые updates');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'photo',
        'photo' => 'https://example.test/user-photo.jpg',
        'caption' => 'User photo caption',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'User photo из UI должен редиректить обратно в чат');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue('https://example.test/user-photo.jpg', $json['result'][0]['message']['photo'][0]['file_id'], 'User photo update содержит Message.photo');
    assertSameValue('User photo caption', $json['result'][0]['message']['caption'], 'User photo update содержит caption');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'document',
        'document' => 'https://example.test/user-file.pdf',
        'caption' => 'User document caption',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'User document из UI должен редиректить обратно в чат');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue('https://example.test/user-file.pdf', $json['result'][0]['message']['document']['file_id'], 'User document update содержит Message.document');
    assertSameValue('user-file.pdf', $json['result'][0]['message']['document']['file_name'], 'User document update содержит file_name');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'location',
        'latitude' => '43.2',
        'longitude' => '132.3',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'User location из UI должен редиректить обратно в чат');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(43.2, $json['result'][0]['message']['location']['latitude'], 'User location update содержит latitude');
    assertSameValue(132.3, $json['result'][0]['message']['location']['longitude'], 'User location update содержит longitude');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'contact',
        'phone_number' => '+71111111111',
        'first_name' => 'User Contact',
        'last_name' => 'Local',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'User contact из UI должен редиректить обратно в чат');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue('+71111111111', $json['result'][0]['message']['contact']['phone_number'], 'User contact update содержит phone_number');
    assertSameValue('User Contact', $json['result'][0]['message']['contact']['first_name'], 'User contact update содержит first_name');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Чат после user structured сообщений должен открываться');
    assertTrueValue(str_contains($chat['body'], 'User photo caption'), 'Чат показывает caption user photo');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/user-file.pdf'), 'Чат показывает user document placeholder');
    assertTrueValue(str_contains($chat['body'], '43.2'), 'Чат показывает user location latitude');
    assertTrueValue(str_contains($chat['body'], '+71111111111'), 'Чат показывает user contact phone');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'text' => 'Reply A',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Reply keyboard button отправляет текст в чат');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue('Reply A', $json['result'][0]['message']['text'], 'Reply keyboard click создает message update');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=' . $nextOffset), 200, true);

    $response = httpRequest('POST', $baseUrl . '/chat/callback', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_id' => '4',
        'callback_data' => 'inline-action',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Inline keyboard callback должен редиректить обратно в чат');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?allowed_updates=' . rawurlencode('["callback_query"]')), 200, true);
    assertSameValue(1, count($json['result']), 'Inline callback создает callback_query update');
    assertSameValue('inline-action', $json['result'][0]['callback_query']['data'], 'callback_query содержит callback_data');
    assertSameValue(false, $json['result'][0]['callback_query']['from']['is_bot'], 'callback_query.from описывает пользователя');
    assertSameValue(4, $json['result'][0]['callback_query']['message']['message_id'], 'callback_query.message ссылается на сообщение с кнопкой');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/answerCallbackQuery', formBody([
        'callback_query_id' => $json['result'][0]['callback_query']['id'],
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(true, $json['result'], 'answerCallbackQuery подтверждает callback');

}

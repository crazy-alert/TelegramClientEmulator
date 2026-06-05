<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiStructuredMethodScenarios(array $context): void {
    extract($context);
    $scenario = '[structured methods] ';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendLocation', json_encode([
        'chat_id' => 1001,
        'latitude' => 43.1155,
        'longitude' => 131.8855,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(14, $json['result']['message_id'], $scenario . 'sendLocation возвращает следующий message_id');
    assertSameValue(43.1155, $json['result']['location']['latitude'], $scenario . 'sendLocation возвращает latitude');
    assertSameValue(131.8855, $json['result']['location']['longitude'], $scenario . 'sendLocation возвращает longitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendLocation', formBody([
        'chat_id' => '1001',
        'longitude' => '131.8855',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "latitude" is required', $json['description'], $scenario . 'sendLocation требует latitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVenue', json_encode([
        'chat_id' => 1001,
        'latitude' => 43.116,
        'longitude' => 131.886,
        'title' => 'Local venue',
        'address' => 'Local address',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(15, $json['result']['message_id'], $scenario . 'sendVenue возвращает следующий message_id');
    assertSameValue('Local venue', $json['result']['venue']['title'], $scenario . 'sendVenue возвращает title');
    assertSameValue('Local address', $json['result']['venue']['address'], $scenario . 'sendVenue возвращает address');
    assertSameValue(43.116, $json['result']['venue']['location']['latitude'], $scenario . 'sendVenue возвращает location.latitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendContact', json_encode([
        'chat_id' => 1001,
        'phone_number' => '+70000000000',
        'first_name' => 'Contact',
        'last_name' => 'User',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(16, $json['result']['message_id'], $scenario . 'sendContact возвращает следующий message_id');
    assertSameValue('+70000000000', $json['result']['contact']['phone_number'], $scenario . 'sendContact возвращает phone_number');
    assertSameValue('Contact', $json['result']['contact']['first_name'], $scenario . 'sendContact возвращает first_name');
    assertSameValue('User', $json['result']['contact']['last_name'], $scenario . 'sendContact возвращает last_name');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDice', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(17, $json['result']['message_id'], $scenario . 'sendDice возвращает следующий message_id');
    assertSameValue('🎲', $json['result']['dice']['emoji'], $scenario . 'sendDice по умолчанию возвращает dice emoji');
    assertSameValue(4, $json['result']['dice']['value'], $scenario . 'sendDice возвращает детерминированное значение');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', json_encode([
        'chat_id' => 1001,
        'question' => 'Choose one',
        'options' => ['A', 'B'],
        'allows_multiple_answers' => true,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(18, $json['result']['message_id'], $scenario . 'sendPoll возвращает следующий message_id');
    assertSameValue('Choose one', $json['result']['poll']['question'], $scenario . 'sendPoll возвращает question');
    assertSameValue('regular', $json['result']['poll']['type'], $scenario . 'sendPoll по умолчанию возвращает regular poll');
    assertSameValue(true, $json['result']['poll']['allows_multiple_answers'], $scenario . 'sendPoll возвращает allows_multiple_answers');
    assertSameValue('A', $json['result']['poll']['options'][0]['text'], $scenario . 'sendPoll возвращает option text');
    assertSameValue(0, $json['result']['poll']['options'][0]['voter_count'], $scenario . 'sendPoll возвращает voter_count');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', json_encode([
        'chat_id' => 1001,
        'question' => 'Quiz question',
        'options' => [
            ['text' => 'Wrong'],
            ['text' => 'Right'],
        ],
        'type' => 'quiz',
        'correct_option_id' => 1,
        'explanation' => 'Because local test',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(19, $json['result']['message_id'], $scenario . 'sendPoll quiz возвращает следующий message_id');
    assertSameValue('quiz', $json['result']['poll']['type'], $scenario . 'sendPoll возвращает quiz type');
    assertSameValue(1, $json['result']['poll']['correct_option_id'], $scenario . 'sendPoll возвращает correct_option_id');
    assertSameValue('Because local test', $json['result']['poll']['explanation'], $scenario . 'sendPoll возвращает explanation');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', formBody([
        'chat_id' => '1001',
        'question' => 'Invalid options',
        'options' => json_encode(['only one'], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: invalid poll parameters', $json['description'], $scenario . 'sendPoll отклоняет невалидные options');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', formBody([
        'chat_id' => '1001',
        'options' => json_encode(['A', 'B'], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "question" is required', $json['description'], $scenario . 'sendPoll требует question');
}

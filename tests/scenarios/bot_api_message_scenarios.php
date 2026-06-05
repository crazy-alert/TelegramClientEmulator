<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiMessageScenarios(array $context): void {
    extract($context);
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
    assertSameValue(4, $json['result']['message_id'], 'sendMessage с inline keyboard возвращает следующий message_id');
    assertSameValue($inlineMarkup, $json['result']['reply_markup'], 'sendMessage возвращает inline reply_markup');

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
    assertSameValue(5, $json['result']['message_id'], 'sendMessage с reply keyboard возвращает следующий message_id');
    assertSameValue($replyMarkup, $json['result']['reply_markup'], 'sendMessage возвращает reply keyboard');

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
    assertSameValue(5, $json['result']['message_id'], 'editMessageText возвращает исходный message_id');
    assertSameValue('Edited reply keyboard', $json['result']['text'], 'editMessageText возвращает обновленный текст');
    assertSameValue($editedMarkup, $json['result']['reply_markup'], 'editMessageText возвращает обновленный reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', formBody([
        'chat_id' => '1001',
        'message_id' => '3',
        'text' => 'Cannot edit user message',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: message to edit not found', $json['description'], 'editMessageText не редактирует сообщения пользователя');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', formBody([
        'chat_id' => '9999',
        'message_id' => '5',
        'text' => 'No chat',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'editMessageText проверяет существование чата');

    $photoMarkup = [
        'inline_keyboard' => [
            [
                [
                    'text' => 'Open photo',
                    'url' => 'https://example.test/photo',
                ],
            ],
        ],
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', json_encode([
        'chat_id' => 1001,
        'photo' => 'https://example.test/photo.jpg',
        'caption' => 'Photo caption',
        'reply_markup' => $photoMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(6, $json['result']['message_id'], 'sendPhoto возвращает следующий message_id');
    assertSameValue('Photo caption', $json['result']['caption'], 'sendPhoto возвращает caption');
    assertSameValue('https://example.test/photo.jpg', $json['result']['photo'][0]['file_id'], 'sendPhoto возвращает Telegram-like photo');
    assertSameValue($photoMarkup, $json['result']['reply_markup'], 'sendPhoto возвращает reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "photo" is required', $json['description'], 'sendPhoto требует photo');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', formBody([
        'chat_id' => '9999',
        'photo' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'sendPhoto проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', json_encode([
        'chat_id' => 1001,
        'document' => 'https://example.test/manual.pdf',
        'caption' => 'Document caption',
        'reply_markup' => $photoMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(7, $json['result']['message_id'], 'sendDocument возвращает следующий message_id');
    assertSameValue('Document caption', $json['result']['caption'], 'sendDocument возвращает caption');
    assertSameValue('https://example.test/manual.pdf', $json['result']['document']['file_id'], 'sendDocument возвращает Telegram-like document');
    assertSameValue('manual.pdf', $json['result']['document']['file_name'], 'sendDocument возвращает file_name');
    assertSameValue($photoMarkup, $json['result']['reply_markup'], 'sendDocument возвращает reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "document" is required', $json['description'], 'sendDocument требует document');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', formBody([
        'chat_id' => '9999',
        'document' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'sendDocument проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendLocation', json_encode([
        'chat_id' => 1001,
        'latitude' => 43.1155,
        'longitude' => 131.8855,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(8, $json['result']['message_id'], 'sendLocation возвращает следующий message_id');
    assertSameValue(43.1155, $json['result']['location']['latitude'], 'sendLocation возвращает latitude');
    assertSameValue(131.8855, $json['result']['location']['longitude'], 'sendLocation возвращает longitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendLocation', formBody([
        'chat_id' => '1001',
        'longitude' => '131.8855',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "latitude" is required', $json['description'], 'sendLocation требует latitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVenue', json_encode([
        'chat_id' => 1001,
        'latitude' => 43.116,
        'longitude' => 131.886,
        'title' => 'Local venue',
        'address' => 'Local address',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(9, $json['result']['message_id'], 'sendVenue возвращает следующий message_id');
    assertSameValue('Local venue', $json['result']['venue']['title'], 'sendVenue возвращает title');
    assertSameValue('Local address', $json['result']['venue']['address'], 'sendVenue возвращает address');
    assertSameValue(43.116, $json['result']['venue']['location']['latitude'], 'sendVenue возвращает location.latitude');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendContact', json_encode([
        'chat_id' => 1001,
        'phone_number' => '+70000000000',
        'first_name' => 'Contact',
        'last_name' => 'User',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(10, $json['result']['message_id'], 'sendContact возвращает следующий message_id');
    assertSameValue('+70000000000', $json['result']['contact']['phone_number'], 'sendContact возвращает phone_number');
    assertSameValue('Contact', $json['result']['contact']['first_name'], 'sendContact возвращает first_name');
    assertSameValue('User', $json['result']['contact']['last_name'], 'sendContact возвращает last_name');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDice', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(11, $json['result']['message_id'], 'sendDice возвращает следующий message_id');
    assertSameValue('🎲', $json['result']['dice']['emoji'], 'sendDice по умолчанию возвращает dice emoji');
    assertSameValue(4, $json['result']['dice']['value'], 'sendDice возвращает детерминированное значение');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', json_encode([
        'chat_id' => 1001,
        'video' => 'https://example.test/video.mp4',
        'caption' => 'Video caption',
        'duration' => 12,
        'width' => 640,
        'height' => 360,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(12, $json['result']['message_id'], 'sendVideo возвращает следующий message_id');
    assertSameValue('https://example.test/video.mp4', $json['result']['video']['file_id'], 'sendVideo возвращает video.file_id');
    assertSameValue(12, $json['result']['video']['duration'], 'sendVideo возвращает duration');
    assertSameValue('Video caption', $json['result']['caption'], 'sendVideo возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "video" is required', $json['description'], 'sendVideo требует video');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', formBody([
        'chat_id' => '9999',
        'video' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'sendVideo проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendAnimation', formBody([
        'chat_id' => '1001',
        'animation' => 'https://example.test/animation.gif',
        'caption' => 'Animation caption',
        'duration' => '5',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(13, $json['result']['message_id'], 'sendAnimation возвращает следующий message_id');
    assertSameValue('https://example.test/animation.gif', $json['result']['animation']['file_id'], 'sendAnimation возвращает animation.file_id');
    assertSameValue('Animation caption', $json['result']['caption'], 'sendAnimation возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendAudio', json_encode([
        'chat_id' => 1001,
        'audio' => 'https://example.test/audio.mp3',
        'caption' => 'Audio caption',
        'duration' => 31,
        'performer' => 'Local performer',
        'title' => 'Local title',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(14, $json['result']['message_id'], 'sendAudio возвращает следующий message_id');
    assertSameValue('https://example.test/audio.mp3', $json['result']['audio']['file_id'], 'sendAudio возвращает audio.file_id');
    assertSameValue('Local performer', $json['result']['audio']['performer'], 'sendAudio возвращает performer');
    assertSameValue('Audio caption', $json['result']['caption'], 'sendAudio возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVoice', formBody([
        'chat_id' => '1001',
        'voice' => 'https://example.test/voice.ogg',
        'duration' => '7',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(15, $json['result']['message_id'], 'sendVoice возвращает следующий message_id');
    assertSameValue('https://example.test/voice.ogg', $json['result']['voice']['file_id'], 'sendVoice возвращает voice.file_id');
    assertSameValue(7, $json['result']['voice']['duration'], 'sendVoice возвращает duration');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideoNote', formBody([
        'chat_id' => '1001',
        'video_note' => 'https://example.test/video-note.mp4',
        'length' => '240',
        'duration' => '8',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(16, $json['result']['message_id'], 'sendVideoNote возвращает следующий message_id');
    assertSameValue('https://example.test/video-note.mp4', $json['result']['video_note']['file_id'], 'sendVideoNote возвращает video_note.file_id');
    assertSameValue(240, $json['result']['video_note']['length'], 'sendVideoNote возвращает length');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendSticker', formBody([
        'chat_id' => '1001',
        'sticker' => 'sticker-file-id',
        'emoji' => '🙂',
        'width' => '512',
        'height' => '512',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(17, $json['result']['message_id'], 'sendSticker возвращает следующий message_id');
    assertSameValue('sticker-file-id', $json['result']['sticker']['file_id'], 'sendSticker возвращает sticker.file_id');
    assertSameValue('🙂', $json['result']['sticker']['emoji'], 'sendSticker возвращает emoji');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', json_encode([
        'chat_id' => 1001,
        'question' => 'Choose one',
        'options' => ['A', 'B'],
        'allows_multiple_answers' => true,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(18, $json['result']['message_id'], 'sendPoll возвращает следующий message_id');
    assertSameValue('Choose one', $json['result']['poll']['question'], 'sendPoll возвращает question');
    assertSameValue('regular', $json['result']['poll']['type'], 'sendPoll по умолчанию возвращает regular poll');
    assertSameValue(true, $json['result']['poll']['allows_multiple_answers'], 'sendPoll возвращает allows_multiple_answers');
    assertSameValue('A', $json['result']['poll']['options'][0]['text'], 'sendPoll возвращает option text');
    assertSameValue(0, $json['result']['poll']['options'][0]['voter_count'], 'sendPoll возвращает voter_count');

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
    assertSameValue(19, $json['result']['message_id'], 'sendPoll quiz возвращает следующий message_id');
    assertSameValue('quiz', $json['result']['poll']['type'], 'sendPoll возвращает quiz type');
    assertSameValue(1, $json['result']['poll']['correct_option_id'], 'sendPoll возвращает correct_option_id');
    assertSameValue('Because local test', $json['result']['poll']['explanation'], 'sendPoll возвращает explanation');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', formBody([
        'chat_id' => '1001',
        'question' => 'Invalid options',
        'options' => json_encode(['only one'], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: invalid poll parameters', $json['description'], 'sendPoll отклоняет невалидные options');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPoll', formBody([
        'chat_id' => '1001',
        'options' => json_encode(['A', 'B'], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "question" is required', $json['description'], 'sendPoll требует question');

}

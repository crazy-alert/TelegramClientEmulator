<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runBotApiMediaMethodScenarios(array $context): void {
    extract($context);
    $scenario = '[media methods] ';
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
    assertSameValue(6, $json['result']['message_id'], $scenario . 'sendPhoto возвращает следующий message_id');
    assertSameValue('Photo caption', $json['result']['caption'], $scenario . 'sendPhoto возвращает caption');
    assertSameValue('https://example.test/photo.jpg', $json['result']['photo'][0]['file_id'], $scenario . 'sendPhoto возвращает Telegram-like photo');
    assertSameValue($photoMarkup, $json['result']['reply_markup'], $scenario . 'sendPhoto возвращает reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "photo" is required', $json['description'], $scenario . 'sendPhoto требует photo');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', formBody([
        'chat_id' => '9999',
        'photo' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], $scenario . 'sendPhoto проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', json_encode([
        'chat_id' => 1001,
        'document' => 'https://example.test/manual.pdf',
        'caption' => 'Document caption',
        'reply_markup' => $photoMarkup,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(7, $json['result']['message_id'], $scenario . 'sendDocument возвращает следующий message_id');
    assertSameValue('Document caption', $json['result']['caption'], $scenario . 'sendDocument возвращает caption');
    assertSameValue('https://example.test/manual.pdf', $json['result']['document']['file_id'], $scenario . 'sendDocument возвращает Telegram-like document');
    assertSameValue('manual.pdf', $json['result']['document']['file_name'], $scenario . 'sendDocument возвращает file_name');
    assertSameValue($photoMarkup, $json['result']['reply_markup'], $scenario . 'sendDocument возвращает reply_markup');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "document" is required', $json['description'], $scenario . 'sendDocument требует document');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', formBody([
        'chat_id' => '9999',
        'document' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], $scenario . 'sendDocument проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', json_encode([
        'chat_id' => 1001,
        'video' => 'https://example.test/video.mp4',
        'caption' => 'Video caption',
        'duration' => 12,
        'width' => 640,
        'height' => 360,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(8, $json['result']['message_id'], $scenario . 'sendVideo возвращает следующий message_id');
    assertSameValue('https://example.test/video.mp4', $json['result']['video']['file_id'], $scenario . 'sendVideo возвращает video.file_id');
    assertSameValue(12, $json['result']['video']['duration'], $scenario . 'sendVideo возвращает duration');
    assertSameValue('Video caption', $json['result']['caption'], $scenario . 'sendVideo возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "video" is required', $json['description'], $scenario . 'sendVideo требует video');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', formBody([
        'chat_id' => '9999',
        'video' => 'file-id',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], $scenario . 'sendVideo проверяет существование чата');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendAnimation', formBody([
        'chat_id' => '1001',
        'animation' => 'https://example.test/animation.gif',
        'caption' => 'Animation caption',
        'duration' => '5',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(9, $json['result']['message_id'], $scenario . 'sendAnimation возвращает следующий message_id');
    assertSameValue('https://example.test/animation.gif', $json['result']['animation']['file_id'], $scenario . 'sendAnimation возвращает animation.file_id');
    assertSameValue('Animation caption', $json['result']['caption'], $scenario . 'sendAnimation возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendAudio', json_encode([
        'chat_id' => 1001,
        'audio' => 'https://example.test/audio.mp3',
        'caption' => 'Audio caption',
        'duration' => 31,
        'performer' => 'Local performer',
        'title' => 'Local title',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(10, $json['result']['message_id'], $scenario . 'sendAudio возвращает следующий message_id');
    assertSameValue('https://example.test/audio.mp3', $json['result']['audio']['file_id'], $scenario . 'sendAudio возвращает audio.file_id');
    assertSameValue('Local performer', $json['result']['audio']['performer'], $scenario . 'sendAudio возвращает performer');
    assertSameValue('Audio caption', $json['result']['caption'], $scenario . 'sendAudio возвращает caption');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVoice', formBody([
        'chat_id' => '1001',
        'voice' => 'https://example.test/voice.ogg',
        'duration' => '7',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(11, $json['result']['message_id'], $scenario . 'sendVoice возвращает следующий message_id');
    assertSameValue('https://example.test/voice.ogg', $json['result']['voice']['file_id'], $scenario . 'sendVoice возвращает voice.file_id');
    assertSameValue(7, $json['result']['voice']['duration'], $scenario . 'sendVoice возвращает duration');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideoNote', formBody([
        'chat_id' => '1001',
        'video_note' => 'https://example.test/video-note.mp4',
        'length' => '240',
        'duration' => '8',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(12, $json['result']['message_id'], $scenario . 'sendVideoNote возвращает следующий message_id');
    assertSameValue('https://example.test/video-note.mp4', $json['result']['video_note']['file_id'], $scenario . 'sendVideoNote возвращает video_note.file_id');
    assertSameValue(240, $json['result']['video_note']['length'], $scenario . 'sendVideoNote возвращает length');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendSticker', formBody([
        'chat_id' => '1001',
        'sticker' => 'sticker-file-id',
        'emoji' => '🙂',
        'width' => '512',
        'height' => '512',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(13, $json['result']['message_id'], $scenario . 'sendSticker возвращает следующий message_id');
    assertSameValue('sticker-file-id', $json['result']['sticker']['file_id'], $scenario . 'sendSticker возвращает sticker.file_id');
    assertSameValue('🙂', $json['result']['sticker']['emoji'], $scenario . 'sendSticker возвращает emoji');
}

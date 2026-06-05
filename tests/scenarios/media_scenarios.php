<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runMediaScenarios(array $context): void {
    extract($context);
    $photoBytes = 'uploaded-photo-bytes';
    $photoHash = hash('sha256', $photoBytes);
    $boundary = '----TelegramClientEmulatorUploadPhoto';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendPhoto', multipartBody([
        'chat_id' => '1001',
        'caption' => 'Uploaded photo caption',
    ], $boundary, [
        'photo' => [
            'filename' => '../photo.png',
            'content' => $photoBytes,
            'content_type' => 'image/png',
        ],
    ]), ['Content-Type: multipart/form-data; boundary=' . $boundary]), 200, true);
    assertSameValue('local-media:' . $photoHash, $json['result']['photo'][0]['file_id'], 'sendPhoto должен принимать multipart upload');
    assertSameValue(strlen($photoBytes), $json['result']['photo'][0]['file_size'], 'sendPhoto должен возвращать размер локального файла');
    assertSameValue('Uploaded photo caption', $json['result']['caption'], 'sendPhoto multipart сохраняет caption');

    $documentBytes = 'uploaded-document-bytes';
    $documentHash = hash('sha256', $documentBytes);
    $boundary = '----TelegramClientEmulatorUploadDocument';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendDocument', multipartBody([
        'chat_id' => '1001',
        'caption' => 'Uploaded document caption',
    ], $boundary, [
        'document' => [
            'filename' => '..\\report.txt',
            'content' => $documentBytes,
            'content_type' => 'text/plain',
        ],
    ]), ['Content-Type: multipart/form-data; boundary=' . $boundary]), 200, true);
    assertSameValue('local-media:' . $documentHash, $json['result']['document']['file_id'], 'sendDocument должен принимать multipart upload');
    assertSameValue('report.txt', $json['result']['document']['file_name'], 'sendDocument должен чистить path traversal из имени файла');
    assertSameValue('text/plain', $json['result']['document']['mime_type'], 'sendDocument должен возвращать mime_type');
    assertSameValue(strlen($documentBytes), $json['result']['document']['file_size'], 'sendDocument должен возвращать размер локального файла');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getFile?file_id=' . rawurlencode('local-media:' . $documentHash)), 200, true);
    assertSameValue('local-media:' . $documentHash, $json['result']['file_id'], 'getFile должен возвращать локальный file_id');
    assertSameValue(substr($documentHash, 0, 16), $json['result']['file_unique_id'], 'getFile должен возвращать стабильный file_unique_id');
    assertSameValue(strlen($documentBytes), $json['result']['file_size'], 'getFile должен возвращать размер файла');
    assertTrueValue(str_starts_with($json['result']['file_path'], $documentHash), 'getFile должен возвращать file_path внутри media storage');
    $documentFilePath = $json['result']['file_path'];

    $download = httpRequest('GET', $baseUrl . '/file/bot' . $token . '/' . rawurlencode($documentFilePath));
    assertSameValue(200, $download['status'], 'Локальная ссылка getFile должна отдавать файл');
    assertSameValue($documentBytes, $download['body'], 'Локальная ссылка должна вернуть сохраненные байты');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getFile?file_id=' . rawurlencode('local-media:' . str_repeat('0', 64))), 400, false);
    assertSameValue('Bad Request: file not found', $json['description'], 'getFile должен отклонять неизвестный file_id');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/file/bot' . $token . '/' . rawurlencode('../report.txt')), 404, false);
    assertSameValue('File not found', $json['description'], 'Локальная отдача должна отклонять path traversal');

    $videoBytes = 'uploaded-video-bytes';
    $videoHash = hash('sha256', $videoBytes);
    $boundary = '----TelegramClientEmulatorUploadVideo';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendVideo', multipartBody([
        'chat_id' => '1001',
        'caption' => 'Uploaded video caption',
        'duration' => '9',
    ], $boundary, [
        'video' => [
            'filename' => 'clip.mp4',
            'content' => $videoBytes,
            'content_type' => 'video/mp4',
        ],
    ]), ['Content-Type: multipart/form-data; boundary=' . $boundary]), 200, true);
    assertSameValue('local-media:' . $videoHash, $json['result']['video']['file_id'], 'sendVideo должен принимать multipart upload');
    assertSameValue('clip.mp4', $json['result']['video']['file_name'], 'sendVideo multipart должен возвращать file_name');
    assertSameValue('video/mp4', $json['result']['video']['mime_type'], 'sendVideo multipart должен возвращать mime_type');
    assertSameValue(strlen($videoBytes), $json['result']['video']['file_size'], 'sendVideo multipart должен возвращать размер файла');
    assertSameValue(9, $json['result']['video']['duration'], 'sendVideo multipart должен сохранять duration');
    assertSameValue('Uploaded video caption', $json['result']['caption'], 'sendVideo multipart должен сохранять caption');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getFile?file_id=' . rawurlencode('local-media:' . $videoHash)), 200, true);
    assertSameValue('local-media:' . $videoHash, $json['result']['file_id'], 'getFile должен находить typed media upload');
    assertSameValue(strlen($videoBytes), $json['result']['file_size'], 'getFile должен вернуть размер typed media');
    assertTrueValue(str_starts_with($json['result']['file_path'], $videoHash), 'getFile должен вернуть file_path typed media');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/file/bot000000:unknown-local-dev-token/' . rawurlencode($documentFilePath)), 404, false);
    assertSameValue('Бот не найден', $json['description'], 'Локальная отдача должна проверять bot token');

    $boundary = '----TelegramClientEmulatorUiUploadPhoto';
    $uiPhotoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
    $response = httpRequest('POST', $baseUrl . '/chat/send', multipartBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'photo',
        'caption' => 'UI upload photo',
    ], $boundary, [
        'photo_file' => [
            'filename' => 'ui-photo.png',
            'content' => $uiPhotoBytes,
            'content_type' => 'image/png',
        ],
    ]), ['Content-Type: multipart/form-data; boundary=' . $boundary]);
    assertSameValue(303, $response['status'], 'UI photo upload должен редиректить обратно в чат');

    $boundary = '----TelegramClientEmulatorUiUploadDocument';
    $response = httpRequest('POST', $baseUrl . '/chat/send', multipartBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'document',
        'caption' => 'UI upload document',
    ], $boundary, [
        'document_file' => [
            'filename' => 'ui-report.pdf',
            'content' => 'ui-document-bytes',
            'content_type' => 'application/pdf',
        ],
    ]), ['Content-Type: multipart/form-data; boundary=' . $boundary]);
    assertSameValue(303, $response['status'], 'UI document upload должен редиректить обратно в чат');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Чат после UI upload должен открываться');
    $chatDom = htmlDocument($chat['body']);
    assertDomXPathExists(
        $chatDom,
        '//img[contains(concat(" ", normalize-space(@class), " "), " media-preview ") and contains(@src, "/file/bot")]',
        'DOM: локальный image media должен показывать compact preview',
    );
    assertDomXPathExists(
        $chatDom,
        '//a[contains(concat(" ", normalize-space(@class), " "), " media-download-link ") and contains(@href, "/file/bot") and normalize-space(.)="Скачать"]',
        'DOM: локальный media block должен показывать ссылку Скачать',
    );
}

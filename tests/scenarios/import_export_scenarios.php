<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runImportExportScenarios(array $context): void {
    extract($context);
    $importExport = httpRequest('GET', $baseUrl . '/import-export');
    assertSameValue(200, $importExport['status'], 'Экран import/export должен открываться');
    assertTrueValue(str_contains($importExport['body'], '/export/bots'), 'Экран import/export должен содержать ссылку экспорта ботов');
    assertTrueValue(str_contains($importExport['body'], '/import/profiles'), 'Экран import/export должен содержать форму импорта пользователей');

    $botExport = assertJsonResponse(httpRequest('GET', $baseUrl . '/export/bots'), 200, true);
    assertSameValue($token, $botExport['bots'][0]['token'], 'Экспорт ботов должен содержать token');
    assertSameValue([], array_intersect(['messages', 'updates', 'delivery_attempts'], array_keys($botExport)), 'Экспорт ботов не должен содержать историю');

    $profileExport = assertJsonResponse(httpRequest('GET', $baseUrl . '/export/profiles'), 200, true);
    assertSameValue(1001, $profileExport['profiles'][0]['user_id'], 'Экспорт пользователей должен содержать user_id');
    assertSameValue([], array_intersect(['messages', 'updates', 'delivery_attempts'], array_keys($profileExport)), 'Экспорт пользователей не должен содержать историю');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/bots', json_encode([
        'bots' => [
            $botExport['bots'][0],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 409, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'Конфликт token'), 'Импорт ботов должен отклонять конфликт token');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/profiles', json_encode([
        'profiles' => [
            $profileExport['profiles'][0],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 409, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'Конфликт user_id'), 'Импорт пользователей должен отклонять конфликт user_id');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/bots', json_encode([
        'bots' => [
            [
                'token' => $importedToken,
                'bot_id' => 654321,
                'username' => 'imported_bot',
                'display_name' => 'Imported Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['created'], 'Импорт ботов должен создавать новый bot payload');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/profiles', json_encode([
        'profiles' => [
            [
                'user_id' => 2002,
                'username' => 'imported_user',
                'first_name' => 'Imported',
                'last_name' => 'User',
                'chat_id' => 2002,
                'chat_type' => 'private',
                'language_code' => 'ru',
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['created'], 'Импорт пользователей должен создавать новый profile payload');

}

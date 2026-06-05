<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runImportedDialogScenarios(array $context): void {
    extract($context);
    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '2',
        'bot_id' => '2',
        'text' => 'Imported dialog message',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение импортированного диалога должно сохраняться');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=2&bot_id=2');
    assertSameValue(200, $chat['status'], 'Импортированный диалог должен открываться');
    assertTrueValue(str_contains($chat['body'], 'Imported dialog message'), 'Импортированный диалог должен показывать свое сообщение');
    assertTrueValue(str_contains($chat['body'], '/chat/clear'), 'Чат должен показывать кнопку очистки диалога');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/chat/clear', formBody([
        'profile_id' => '2',
        'bot_id' => '2',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'подтвердить'), 'Очистка диалога требует подтверждения');

    $response = httpRequest('POST', $baseUrl . '/chat/clear', formBody([
        'profile_id' => '2',
        'bot_id' => '2',
        'confirm_clear' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Очистка выбранного диалога должна редиректить обратно в чат');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=2&bot_id=2');
    assertSameValue(200, $chat['status'], 'Импортированный диалог после очистки должен открываться');
    assertTrueValue(!str_contains($chat['body'], 'Imported dialog message'), 'Очистка должна удалить сообщения только выбранного диалога');
    assertTrueValue(str_contains($chat['body'], 'Диалог пуст'), 'Очищенный диалог должен быть пустым');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Основной диалог после очистки другого диалога должен открываться');
    assertTrueValue(str_contains($chat['body'], 'Edited reply keyboard'), 'Очистка импортированного диалога не должна удалить основной диалог');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '2',
        'bot_id' => '2',
        'text' => 'Imported confirmed update',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение для confirmed update должно сохраняться');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $importedToken . '/getUpdates', json_encode([
        'limit' => 1,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue('Imported confirmed update', $json['result'][0]['message']['text'], 'Импортированный бот должен получить update');
    $nextOffset = ((int) $json['result'][0]['update_id']) + 1;
    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $importedToken . '/getUpdates?offset=' . $nextOffset), 200, true);

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '2',
        'bot_id' => '2',
        'text' => 'Imported pending update',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение для pending update должно сохраняться');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=2');
    assertSameValue(200, $updatesPage['status'], 'Updates импортированного бота должны открываться');
    assertTrueValue(str_contains($updatesPage['body'], '>confirmed<'), 'Перед очисткой у выбранного бота есть confirmed update');
    assertTrueValue(str_contains($updatesPage['body'], '>pending<'), 'Перед очисткой у выбранного бота есть pending update');
    assertTrueValue(str_contains($updatesPage['body'], '/updates/clear'), 'Экран updates должен показывать кнопку очистки для выбранного бота');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/updates/clear', formBody([
        'bot_id' => '2',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'подтвердить'), 'Очистка updates требует подтверждения');

    $response = httpRequest('POST', $baseUrl . '/updates/clear', formBody([
        'bot_id' => '2',
        'confirm_clear' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Очистка pending/confirmed updates выбранного бота должна редиректить');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=2');
    assertSameValue(200, $updatesPage['status'], 'Updates импортированного бота после очистки должны открываться');
    assertTrueValue(!str_contains($updatesPage['body'], 'Imported confirmed update'), 'Очистка updates должна удалить confirmed update выбранного бота');
    assertTrueValue(!str_contains($updatesPage['body'], 'Imported pending update'), 'Очистка updates должна удалить pending update выбранного бота');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1');
}

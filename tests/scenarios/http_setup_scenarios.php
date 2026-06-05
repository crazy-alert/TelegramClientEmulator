<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runHttpSetupScenarios(array $context): void {
    extract($context);
    $dashboard = httpRequest('GET', $baseUrl . '/');
    assertSameValue(200, $dashboard['status'], 'Панель должна открываться');
    assertTrueValue(str_contains($dashboard['body'], 'Webhook delivery'), 'Панель должна показывать настройку webhook delivery');
    assertTrueValue(str_contains($dashboard['body'], 'value="10000"'), 'Панель должна показывать дефолтный webhook timeout');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/settings/webhook-timeout', formBody([
        'webhook_timeout_ms' => '999',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'от 1000 до 60000 мс'), 'Webhook timeout валидирует нижнюю границу');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/settings/webhook-timeout', formBody([
        'webhook_timeout_ms' => '60001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertTrueValue(str_contains((string) ($json['error'] ?? ''), 'от 1000 до 60000 мс'), 'Webhook timeout валидирует верхнюю границу');

    $response = httpRequest('POST', $baseUrl . '/settings/webhook-timeout', formBody([
        'webhook_timeout_ms' => '1500',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сохранение webhook timeout должно редиректить на панель');

    $dashboard = httpRequest('GET', $baseUrl . '/');
    assertSameValue(200, $dashboard['status'], 'Панель после настройки timeout должна открываться');
    assertTrueValue(str_contains($dashboard['body'], 'value="1500"'), 'Панель должна показывать сохраненный webhook timeout');

    $response = httpRequest('POST', $baseUrl . '/bots', formBody([
        'token' => 'bad-token',
        'bot_id' => 'abc',
        'username' => 'bad username',
        'display_name' => '',
        'delivery_mode' => 'bad_mode',
        'webhook_url' => 'not-a-url',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(422, $response['status'], 'Некорректная форма бота должна возвращать 422');
    assertTrueValue(str_contains($response['body'], 'class="field-error"'), 'Форма бота должна показывать inline validation');
    assertTrueValue(str_contains($response['body'], 'Token должен выглядеть'), 'Форма бота должна показывать ошибку token рядом с полем');
    assertTrueValue(str_contains($response['body'], 'Webhook URL должен быть корректным'), 'Форма бота должна показывать ошибку webhook URL');

    $response = httpRequest('POST', $baseUrl . '/bots', formBody([
        'token' => $token,
        'bot_id' => '123456',
        'username' => 'local_bot',
        'display_name' => 'Local Bot',
        'delivery_mode' => 'long_polling',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Создание бота должно редиректить на список');

    $response = httpRequest('POST', $baseUrl . '/profiles', formBody([
        'user_id' => '0',
        'username' => 'bad username',
        'first_name' => '',
        'last_name' => 'User',
        'chat_id' => 'abc',
        'chat_type' => 'bad_type',
        'language_code' => 'РУ',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(422, $response['status'], 'Некорректная форма пользователя должна возвращать 422');
    assertTrueValue(str_contains($response['body'], 'class="field-error"'), 'Форма пользователя должна показывать inline validation');
    assertTrueValue(str_contains($response['body'], 'User ID должен быть'), 'Форма пользователя должна показывать ошибку user_id');
    assertTrueValue(str_contains($response['body'], 'Chat ID должен быть'), 'Форма пользователя должна показывать ошибку chat_id');

    $response = httpRequest('POST', $baseUrl . '/profiles', formBody([
        'user_id' => '1001',
        'username' => 'dev_user',
        'first_name' => 'Dev',
        'last_name' => 'User',
        'chat_id' => '1001',
        'chat_type' => 'private',
        'language_code' => 'en',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Создание пользователя должно редиректить на список');

}

<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runWebhookRetryScenarios(array $context): void {
    extract($context);

    $response = httpRequest('POST', $baseUrl . '/settings/webhook-retry', formBody([
        'webhook_retry_max_attempts' => '3',
        'webhook_retry_delay_ms' => '0',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Настройки development webhook retry должны сохраняться');

    $dashboard = httpRequest('GET', $baseUrl . '/');
    assertSameValue(200, $dashboard['status'], 'Панель после настройки retry должна открываться');
    assertTrueValue(str_contains($dashboard['body'], 'Development retry'), 'Панель должна показывать настройки development retry');
    assertTrueValue(str_contains($dashboard['body'], 'name="webhook_retry_max_attempts"'), 'Панель должна показывать max attempts');
    assertTrueValue(str_contains($dashboard['body'], 'value="3"'), 'Панель должна сохранять max attempts');
    assertTrueValue(str_contains($dashboard['body'], 'не production scheduler'), 'Панель должна явно документировать локальное ограничение retry');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', formBody([
        'url' => 'http://127.0.0.1:' . $receiverPort . '/receiver.php?sequence=500,500,202',
        'secret_token' => 'test-secret',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue('Webhook was set', $json['description'], 'setWebhook должен принять sequence URL для automatic retry');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'text' => 'Automatic retry sequence',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение с automatic retry должно редиректить обратно в чат');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1&queue_state=delivered');
    assertSameValue(200, $updatesPage['status'], 'Automatic retry должен оставить успешный update delivered');
    assertTrueValue(str_contains($updatesPage['body'], 'Automatic retry sequence'), 'Automatic retry должен доставить update после коротких retry');

    $attempts = httpRequest('GET', $baseUrl . '/delivery-attempts?bot_id=1');
    assertSameValue(200, $attempts['status'], 'Delivery attempts после automatic retry должны открываться');
    assertTrueValue(substr_count($attempts['body'], 'Automatic retry sequence') >= 3, 'Automatic retry должен логировать каждую попытку');
    assertTrueValue(str_contains($attempts['body'], 'Webhook returned HTTP 500'), 'Automatic retry не должен скрывать ошибки промежуточных попыток');
    assertTrueValue(str_contains($attempts['body'], '202<br>'), 'Automatic retry должен логировать финальную успешную попытку');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', formBody([
        'url' => 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=500',
        'secret_token' => 'test-secret',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue('Webhook was set', $json['description'], 'setWebhook должен вернуть failed URL перед batch retry');

    foreach (['Batch retry failed A', 'Batch retry failed B'] as $text) {
        $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
            'profile_id' => '1',
            'bot_id' => '1',
            'text' => $text,
        ]), ['Content-Type: application/x-www-form-urlencoded']);
        assertSameValue(303, $response['status'], 'Сообщение для batch retry должно редиректить обратно в чат');
    }

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1&queue_state=failed');
    assertSameValue(200, $updatesPage['status'], 'Экран failed updates должен открываться перед batch retry');
    assertTrueValue(str_contains($updatesPage['body'], '/updates/retry-failed'), 'Экран updates выбранного бота должен показывать batch retry failed webhook updates');
    assertTrueValue(str_contains($updatesPage['body'], 'Retry failed webhook updates'), 'Batch retry должен быть явно помечен как development helper');

    $response = httpRequest('POST', $baseUrl . '/updates/retry-failed', formBody([
        'bot_id' => '1',
        'retry_limit' => '2',
        'retry_delay_ms' => '0',
        'confirm_retry' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Batch retry failed updates должен редиректить на delivery attempts');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1&queue_state=failed');
    assertSameValue(200, $updatesPage['status'], 'Экран failed updates после неуспешного batch retry должен открываться');
    assertTrueValue(str_contains($updatesPage['body'], 'Batch retry failed A'), 'Неуспешный batch retry должен оставить первый update failed');
    assertTrueValue(str_contains($updatesPage['body'], 'Batch retry failed B'), 'Неуспешный batch retry должен оставить второй update failed');

    $attempts = httpRequest('GET', $baseUrl . '/delivery-attempts?bot_id=1');
    assertSameValue(200, $attempts['status'], 'Delivery attempts после failed batch retry должны открываться');
    assertTrueValue(str_contains($attempts['body'], 'Batch retry failed A'), 'Batch retry должен логировать отдельную попытку первого update');
    assertTrueValue(str_contains($attempts['body'], 'Batch retry failed B'), 'Batch retry должен логировать отдельную попытку второго update');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', formBody([
        'url' => 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=202',
        'secret_token' => 'test-secret',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue('Webhook was set', $json['description'], 'setWebhook должен вернуть successful URL перед successful batch retry');

    $response = httpRequest('POST', $baseUrl . '/updates/retry-failed', formBody([
        'bot_id' => '1',
        'retry_limit' => '10',
        'retry_delay_ms' => '0',
        'confirm_retry' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Successful batch retry failed updates должен редиректить на delivery attempts');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1&queue_state=delivered');
    assertSameValue(200, $updatesPage['status'], 'Экран delivered updates после successful batch retry должен открываться');
    assertTrueValue(str_contains($updatesPage['body'], 'Batch retry failed A'), 'Successful batch retry должен перевести первый update в delivered');
    assertTrueValue(str_contains($updatesPage['body'], 'Batch retry failed B'), 'Successful batch retry должен перевести второй update в delivered');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteWebhook', formBody([
        'drop_pending_updates' => 'true',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(true, $json['result'], 'deleteWebhook после batch retry возвращает true');
}

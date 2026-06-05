<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runWebhookScenarios(array $context): void {
    extract($context);
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', formBody([
        'url' => 'not-a-url',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: invalid webhook URL', $json['description'], 'setWebhook отклоняет некорректный URL');

    $boundary = '----TelegramClientEmulatorTestBoundary';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', multipartBody([
        'url' => 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=500',
        'secret_token' => 'test-secret',
        'drop_pending_updates' => '1',
    ], $boundary), ['Content-Type: multipart/form-data; boundary=' . $boundary]), 200, true);
    assertSameValue(true, $json['result'], 'setWebhook должен принимать multipart/form-data');
    assertSameValue('Webhook was set', $json['description'], 'setWebhook возвращает Telegram-like описание');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/getUpdates'), 409, false);
    assertSameValue(409, $json['error_code'], 'getUpdates конфликтует с активным webhook');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getWebhookInfo'), 200, true);
    assertSameValue('http://127.0.0.1:' . $receiverPort . '/receiver.php?status=500', $json['result']['url'], 'getWebhookInfo возвращает webhook URL');
    assertSameValue(false, $json['result']['has_custom_certificate'], 'getWebhookInfo возвращает has_custom_certificate=false');
    assertSameValue(40, $json['result']['max_connections'], 'getWebhookInfo возвращает max_connections');
    assertSameValue(0, $json['result']['pending_update_count'], 'getWebhookInfo возвращает pending_update_count');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'text' => '/webhook_failed',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение при webhook должно редиректить обратно в чат');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Чат после failed webhook должен открываться');
    assertTrueValue(str_contains($chat['body'], 'htmx.org@1.9.12'), 'Layout должен подключать HTMX');
    assertTrueValue(str_contains($chat['body'], 'id="chat-live"'), 'Чат должен иметь live-контейнер для polling-обновлений');
    assertTrueValue(str_contains($chat['body'], 'hx-get="/chat/fragment?profile_id=1&amp;bot_id=1"'), 'Чат должен polling-обновлять выбранную пару');
    assertTrueValue(str_contains($chat['body'], 'hx-trigger="every 3s"'), 'Чат должен периодически обновлять фрагмент');
    assertTrueValue(str_contains($chat['body'], 'id="chat-messages"'), 'История чата должна иметь контейнер для автопрокрутки');
    assertTrueValue(str_contains($chat['body'], 'data-chat-messages'), 'История чата должна помечаться для JS автопрокрутки');
    assertTrueValue(str_contains($chat['body'], 'max-height: 750px'), 'История чата должна быть достаточно высокой для небольшого экрана');
    assertTrueValue(str_contains($chat['body'], 'htmx:beforeSwap'), 'Layout должен запоминать позицию истории перед HTMX-обновлением');
    assertTrueValue(str_contains($chat['body'], 'htmx:afterSwap'), 'Layout должен обрабатывать историю после HTMX-обновления');
    assertTrueValue(str_contains($chat['body'], 'isChatMessagesNearBottom'), 'Layout должен проверять, был ли пользователь внизу истории');
    assertTrueValue(str_contains($chat['body'], 'shouldStickChatMessagesToBottom'), 'Layout должен прокручивать историю вниз только при sticky-состоянии');
    assertTrueValue(str_contains($chat['body'], 'previousChatMessagesScrollTop'), 'Layout должен сохранять позицию истории, если пользователь не внизу');
    assertTrueValue(str_contains($chat['body'], 'queue_state</th>'), 'Inspector должен показывать update');
    assertTrueValue(str_contains($chat['body'], '>failed<'), 'Failed webhook должен оставить update в состоянии failed');
    assertTrueValue(str_contains($chat['body'], '/updates/1/resend'), 'Для failed update должна быть кнопка resend');
    $messagesPosition = strpos($chat['body'], 'id="chat-messages"');
    $commandsPosition = strpos($chat['body'], 'bot-command-select');
    $inspectorPosition = strpos($chat['body'], 'queue_state</th>');
    assertTrueValue(
        $messagesPosition !== false && $commandsPosition !== false && $messagesPosition < $commandsPosition,
        'История чата должна быть выше выбора команд',
    );
    assertTrueValue(
        $commandsPosition !== false && $inspectorPosition !== false && $commandsPosition < $inspectorPosition,
        'Inspector должен быть ниже основных элементов чата',
    );

    $fragment = httpRequest('GET', $baseUrl . '/chat/fragment?profile_id=1&bot_id=1');
    assertSameValue(200, $fragment['status'], 'HTMX-фрагмент чата должен открываться');
    assertTrueValue(!str_contains($fragment['body'], '<h1>Чат</h1>'), 'HTMX-фрагмент не должен возвращать полную страницу чата');
    assertTrueValue(!str_contains($fragment['body'], '<textarea'), 'HTMX-фрагмент не должен перерисовывать поле ввода сообщения');
    assertTrueValue(!str_contains($fragment['body'], 'bot-command-select'), 'HTMX-фрагмент не должен перерисовывать select команд бота');
    assertTrueValue(str_contains($fragment['body'], 'id="chat-messages"'), 'HTMX-фрагмент должен возвращать историю сообщений');
    assertTrueValue(!str_contains($fragment['body'], 'queue_state</th>'), 'HTMX-фрагмент не должен перерисовывать inspector');
    assertTrueValue(!str_contains($fragment['body'], 'Raw payload (JSON)'), 'HTMX-фрагмент не должен перерисовывать Raw payload');
    assertTrueValue(!str_contains($fragment['body'], 'Webhook delivery'), 'HTMX-фрагмент не должен перерисовывать webhook delivery');
    assertTrueValue(!str_contains($fragment['body'], '/updates/1/resend'), 'HTMX-фрагмент не должен перерисовывать кнопку resend');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setWebhook', formBody([
        'url' => 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=202',
        'secret_token' => 'test-secret',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue('Webhook was set', $json['description'], 'setWebhook должен обновить URL перед resend');

    $response = httpRequest('POST', $baseUrl . '/updates/1/resend');
    assertSameValue(303, $response['status'], 'Resend failed webhook должен редиректить обратно в чат');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Чат после resend должен открываться');
    assertTrueValue(str_contains($chat['body'], '>delivered<'), 'Успешный resend должен перевести update в delivered');
    assertTrueValue(!str_contains($chat['body'], '/updates/1/resend'), 'После successful resend кнопка resend должна исчезнуть');

    $attempts = httpRequest('GET', $baseUrl . '/delivery-attempts');
    assertSameValue(200, $attempts['status'], 'Экран delivery attempts должен открываться');
    assertTrueValue(str_contains($attempts['body'], 'Webhook delivery attempts'), 'Экран delivery attempts должен иметь заголовок');
    assertTrueValue(str_contains($attempts['body'], 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=500'), 'Список attempts показывает failed webhook URL');
    assertTrueValue(str_contains($attempts['body'], 'http://127.0.0.1:' . $receiverPort . '/receiver.php?status=202'), 'Список attempts показывает successful resend URL');
    assertTrueValue(str_contains($attempts['body'], '/chat?profile_id=1&bot_id=1'), 'Список attempts содержит ссылку в чат');

    $attempts = httpRequest('GET', $baseUrl . '/delivery-attempts?bot_id=1&update_id=100000001');
    assertSameValue(200, $attempts['status'], 'Экран delivery attempts с фильтром должен открываться');
    assertTrueValue(str_contains($attempts['body'], 'value="100000001"'), 'Фильтр update_id должен сохранять значение');
    assertTrueValue(str_contains($attempts['body'], 'selected'), 'Фильтр bot_id должен сохранять выбранного бота');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates');
    assertSameValue(200, $updatesPage['status'], 'Экран updates должен открываться');
    assertTrueValue(str_contains($updatesPage['body'], '<h1>Updates</h1>'), 'Экран updates должен иметь заголовок');
    assertTrueValue(str_contains($updatesPage['body'], '100000001'), 'Экран updates должен показывать update_id');
    assertTrueValue(str_contains($updatesPage['body'], '>delivered<'), 'Экран updates должен показывать queue_state');
    assertTrueValue(str_contains($updatesPage['body'], '/chat?profile_id=1&amp;bot_id=1'), 'Экран updates должен содержать ссылку в чат');
    assertTrueValue(str_contains($updatesPage['body'], '/delivery-attempts?bot_id=1&amp;update_id=100000001'), 'Экран updates должен содержать ссылку на delivery attempts');

    $updatesPage = httpRequest('GET', $baseUrl . '/updates?bot_id=1&profile_id=1&queue_state=delivered&update_id=100000001');
    assertSameValue(200, $updatesPage['status'], 'Экран updates с фильтрами должен открываться');
    assertTrueValue(str_contains($updatesPage['body'], 'value="100000001"'), 'Фильтр updates update_id должен сохранять значение');
    assertTrueValue(str_contains($updatesPage['body'], 'delivered'), 'Фильтр updates queue_state должен сохранять значение');
    assertTrueValue(str_contains($updatesPage['body'], '/updates/retry-failed'), 'Экран updates выбранного бота должен показывать batch retry failed webhook updates');
    assertTrueValue(str_contains($updatesPage['body'], 'Retry failed webhook updates'), 'Batch retry должен быть явно помечен как development helper');

    $inspector = httpRequest('GET', $baseUrl . '/request-inspector?token=' . rawurlencode($token) . '&method=sendMessage');
    assertSameValue(200, $inspector['status'], 'Request inspector должен открываться');
    assertTrueValue(str_contains($inspector['body'], 'Bot API request/response'), 'Inspector должен показывать Bot API секцию');
    assertTrueValue(str_contains($inspector['body'], 'Webhook delivery request/response'), 'Inspector должен показывать webhook секцию');
    assertTrueValue(str_contains($inspector['body'], 'sendMessage'), 'Inspector должен фильтровать Bot API method');
    assertTrueValue(str_contains($inspector['body'], '123456:***'), 'Inspector должен показывать замаскированный bot token');
    assertTrueValue(!str_contains($inspector['body'], $token), 'Inspector не должен раскрывать raw bot token');
    assertTrueValue(!str_contains($inspector['body'], 'test-secret'), 'Inspector не должен раскрывать webhook secret token');
    assertTrueValue(str_contains($inspector['body'], 'HTTP 200'), 'Inspector должен показывать response status');
    assertTrueValue(str_contains($inspector['body'], 'Body pretty JSON'), 'Inspector должен показывать pretty JSON секции');
    assertTrueValue(str_contains($inspector['body'], 'curl -X'), 'Inspector должен показывать copy-friendly curl блок');
    assertTrueValue(str_contains($inspector['body'], '/updates?update_id=100000001'), 'Inspector должен связывать webhook attempt с update context');

    $inspector = httpRequest('GET', $baseUrl . '/request-inspector?response_status=400&ok_false=1');
    assertSameValue(200, $inspector['status'], 'Request inspector с фильтрами status/ok=false должен открываться');
    assertTrueValue(str_contains($inspector['body'], 'value="400"'), 'Inspector должен сохранять фильтр HTTP status');
    assertTrueValue(str_contains($inspector['body'], 'checked'), 'Inspector должен сохранять фильтр ok=false');
    assertTrueValue(str_contains($inspector['body'], 'ok=false'), 'Inspector должен показывать ok=false ответы');
    assertTrueValue(str_contains($inspector['body'], 'HTTP 400'), 'Inspector должен фильтровать HTTP status');
    assertTrueValue(!str_contains($inspector['body'], $token), 'Inspector с фильтрами не должен раскрывать raw bot token');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteWebhook', formBody([
        'drop_pending_updates' => 'true',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(true, $json['result'], 'deleteWebhook возвращает true');
    assertSameValue('Webhook was deleted', $json['description'], 'deleteWebhook возвращает Telegram-like описание');
}

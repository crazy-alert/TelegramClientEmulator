<?php

declare(strict_types=1);

function runHttpTests(string $baseUrl, int $receiverPort): void {
    $token = '123456:local-dev-token-test';
    $importedToken = '654321:imported-local-dev-token';

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

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMe'), 200, true);
    assertSameValue(123456, $json['result']['id'], 'getMe возвращает bot id');
    assertSameValue(true, $json['result']['is_bot'], 'getMe возвращает User.is_bot=true');
    assertSameValue('Local Bot', $json['result']['first_name'], 'getMe возвращает имя бота');
    assertSameValue('local_bot', $json['result']['username'], 'getMe возвращает username бота');
    assertArrayHasKeyValue('can_join_groups', $json['result'], 'getMe возвращает Bot API capability fields');

    $commands = [
        [
            'command' => 'start',
            'description' => 'Начать диалог',
        ],
        [
            'command' => 'help',
            'description' => 'Показать помощь',
        ],
    ];
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setMyCommands', json_encode([
        'commands' => $commands,
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(true, $json['result'], 'setMyCommands возвращает true');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue($commands, $json['result'], 'getMyCommands возвращает сохраненные команды');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/setMyCommands', formBody([
        'commands' => json_encode([
            [
                'command' => 'InvalidCommand',
                'description' => 'Некорректная команда',
            ],
        ], JSON_THROW_ON_ERROR),
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue(400, $json['error_code'], 'setMyCommands валидирует формат команд');

    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot000000:missing-token/getMe'), 404, false);
    assertSameValue(404, $json['error_code'], 'Неизвестный token возвращает Telegram-like 404');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', json_encode([
        'chat_id' => 1001,
        'text' => 'Hello from JSON',
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['result']['message_id'], 'sendMessage JSON возвращает первый message_id');
    assertSameValue(123456, $json['result']['from']['id'], 'sendMessage возвращает Message.from для бота');
    assertSameValue(true, $json['result']['from']['is_bot'], 'sendMessage возвращает Message.from.is_bot=true');
    assertSameValue(1001, $json['result']['chat']['id'], 'sendMessage возвращает Chat.id');
    assertSameValue('private', $json['result']['chat']['type'], 'sendMessage возвращает Chat.type');
    assertSameValue('Hello from JSON', $json['result']['text'], 'sendMessage возвращает текст');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '1001',
        'text' => 'Hello from form',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(2, $json['result']['message_id'], 'sendMessage form-urlencoded возвращает следующий message_id');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '1001',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "text" is required', $json['description'], 'sendMessage требует text');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendMessage', formBody([
        'chat_id' => '9999',
        'text' => 'No chat',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: chat not found', $json['description'], 'sendMessage проверяет существование чата');

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

    $inspector = httpRequest('GET', $baseUrl . '/request-inspector?token=' . rawurlencode($token) . '&method=sendMessage');
    assertSameValue(200, $inspector['status'], 'Request inspector должен открываться');
    assertTrueValue(str_contains($inspector['body'], 'Bot API request/response'), 'Inspector должен показывать Bot API секцию');
    assertTrueValue(str_contains($inspector['body'], 'Webhook delivery request/response'), 'Inspector должен показывать webhook секцию');
    assertTrueValue(str_contains($inspector['body'], 'sendMessage'), 'Inspector должен фильтровать Bot API method');
    assertTrueValue(str_contains($inspector['body'], '123456:***'), 'Inspector должен показывать замаскированный bot token');
    assertTrueValue(!str_contains($inspector['body'], $token), 'Inspector не должен раскрывать raw bot token');
    assertTrueValue(!str_contains($inspector['body'], 'test-secret'), 'Inspector не должен раскрывать webhook secret token');
    assertTrueValue(str_contains($inspector['body'], 'HTTP 200'), 'Inspector должен показывать response status');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteWebhook', formBody([
        'drop_pending_updates' => 'true',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 200, true);
    assertSameValue(true, $json['result'], 'deleteWebhook возвращает true');
    assertSameValue('Webhook was deleted', $json['description'], 'deleteWebhook возвращает Telegram-like описание');

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

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Страница чата должна открываться');
    assertTrueValue(str_contains($chat['body'], '/start'), 'Чат показывает сохраненные команды');
    assertTrueValue(str_contains($chat['body'], 'white-space: pre-line'), 'Текст сообщений должен сохранять переносы строк без лишних пробелов');
    assertTrueValue(!str_contains($chat['body'], 'white-space: pre-wrap'), 'Текст сообщений не должен раздувать блоки через pre-wrap');
    assertTrueValue(str_contains($chat['body'], 'Inline action'), 'Чат показывает inline keyboard');
    assertTrueValue(str_contains($chat['body'], 'Edited reply keyboard'), 'Чат показывает отредактированное сообщение бота');
    assertTrueValue(str_contains($chat['body'], 'Photo caption'), 'Чат показывает caption photo-сообщения');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/photo.jpg'), 'Чат показывает photo placeholder');
    assertTrueValue(str_contains($chat['body'], 'Document caption'), 'Чат показывает caption document-сообщения');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/manual.pdf'), 'Чат показывает document placeholder');
    assertTrueValue(str_contains($chat['body'], 'Location'), 'Чат показывает location-сообщение');
    assertTrueValue(str_contains($chat['body'], '43.1155'), 'Чат показывает latitude location-сообщения');
    assertTrueValue(str_contains($chat['body'], 'Venue'), 'Чат показывает venue-сообщение');
    assertTrueValue(str_contains($chat['body'], 'Local venue'), 'Чат показывает title venue-сообщения');
    assertTrueValue(str_contains($chat['body'], 'Contact'), 'Чат показывает contact-сообщение');
    assertTrueValue(str_contains($chat['body'], '+70000000000'), 'Чат показывает phone_number contact-сообщения');
    assertTrueValue(str_contains($chat['body'], 'Dice'), 'Чат показывает dice-сообщение');
    assertTrueValue(str_contains($chat['body'], 'value 4'), 'Чат показывает value dice-сообщения');
    assertTrueValue(str_contains($chat['body'], 'Video'), 'Чат показывает video-сообщение');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/video.mp4'), 'Чат показывает video placeholder');
    assertTrueValue(str_contains($chat['body'], 'Animation'), 'Чат показывает animation-сообщение');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/animation.gif'), 'Чат показывает animation placeholder');
    assertTrueValue(str_contains($chat['body'], 'Audio'), 'Чат показывает audio-сообщение');
    assertTrueValue(str_contains($chat['body'], 'Local title'), 'Чат показывает audio title');
    assertTrueValue(str_contains($chat['body'], 'Voice'), 'Чат показывает voice-сообщение');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/voice.ogg'), 'Чат показывает voice placeholder');
    assertTrueValue(str_contains($chat['body'], 'Video note'), 'Чат показывает video note сообщение');
    assertTrueValue(str_contains($chat['body'], 'https://example.test/video-note.mp4'), 'Чат показывает video note placeholder');
    assertTrueValue(str_contains($chat['body'], 'Sticker'), 'Чат показывает sticker-сообщение');
    assertTrueValue(str_contains($chat['body'], 'sticker-file-id'), 'Чат показывает sticker placeholder');
    assertTrueValue(str_contains($chat['body'], 'Poll'), 'Чат показывает poll-сообщение');
    assertTrueValue(str_contains($chat['body'], 'Choose one'), 'Чат показывает poll question');
    assertTrueValue(str_contains($chat['body'], 'Quiz question'), 'Чат показывает quiz question');
    assertTrueValue(str_contains($chat['body'], 'Reply A'), 'Чат показывает reply keyboard');
    assertTrueValue(str_contains($chat['body'], 'class="chat-compose"'), 'Чат должен иметь компактную зону reply keyboard и ввода');
    assertTrueValue(str_contains($chat['body'], '@media (max-width: 560px)'), 'Compose-зона должна складываться вертикально только на смартфонах');
    assertTrueValue(!str_contains($chat['body'], '@media (max-width: 720px)'), 'Compose-зона не должна складываться на небольшом экране ноутбука');
    assertTrueValue(str_contains($chat['body'], 'class="bot-command-select"'), 'Команды бота должны быть доступны через select');
    assertTrueValue(str_contains($chat['body'], '<details class="panel bot-command-picker">'), 'Команды бота должны быть спрятаны в раскрывающийся блок');
    assertTrueValue(str_contains($chat['body'], '<details class="panel chat-structured-inputs">'), 'Вложения пользователя должны быть спрятаны в раскрывающийся блок');
    assertTrueValue(str_contains($chat['body'], 'onchange="if (this.value !== \'\') { this.form.submit(); }"'), 'Выбор команды должен сразу отправлять форму');
    assertTrueValue(!str_contains($chat['body'], '<h2>Команды бота</h2>'), 'Команды бота не должны занимать отдельную верхнюю панель');

    $chatDom = htmlDocument($chat['body']);
    assertDomXPathExists(
        $chatDom,
        '//div[contains(concat(" ", normalize-space(@class), " "), " chat-compose ")]//form[contains(concat(" ", normalize-space(@class), " "), " chat-message-form ")]//textarea[@name="text"]',
        'DOM: форма отправки сообщения должна содержать textarea text',
    );
    assertDomXPathExists(
        $chatDom,
        '//form[@method="post" and @action="/chat/callback"]//input[@type="hidden" and @name="callback_data" and @value="inline-action"]',
        'DOM: inline keyboard callback должен быть формой /chat/callback с callback_data',
    );
    assertDomXPathExists(
        $chatDom,
        '//div[contains(concat(" ", normalize-space(@class), " "), " chat-compose ")]//div[contains(concat(" ", normalize-space(@class), " "), " chat-input-tools ")]//form[@method="post" and @action="/chat/send"]//button[contains(normalize-space(.), "Reply A")]',
        'DOM: reply keyboard button должен отправлять форму /chat/send',
    );
    assertDomXPathExists(
        $chatDom,
        '//details[contains(concat(" ", normalize-space(@class), " "), " bot-command-picker ")]//select[contains(concat(" ", normalize-space(@class), " "), " bot-command-select ") and @name="text"]',
        'DOM: команды бота должны быть select[name=text] внутри details',
    );
    assertDomXPathExists(
        $chatDom,
        '//details[contains(concat(" ", normalize-space(@class), " "), " chat-structured-inputs ")]//input[@type="hidden" and @name="message_type" and @value="photo"]',
        'DOM: вложения должны содержать форму photo',
    );
    assertDomXPathExists(
        $chatDom,
        '//details[contains(concat(" ", normalize-space(@class), " "), " chat-structured-inputs ")]//input[@type="hidden" and @name="message_type" and @value="location"]',
        'DOM: вложения должны содержать форму location',
    );

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'text' => '/help',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], '/chat/send должен редиректить обратно в чат');
    assertSameValue(null, $response['json'], 'Редирект /chat/send не обязан быть JSON');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Страница чата после команды должна открываться');
    assertTrueValue(str_contains($chat['body'], 'class="message-command"'), 'Команда в истории сообщений должна быть кликабельной');
    assertTrueValue(str_contains($chat['body'], 'value="/help"'), 'Кликабельная команда в истории должна отправлять текст команды');
    assertTrueValue(!str_contains($chat['body'], "class=\"message-command\" method=\"post\" action=\"/chat/send\" style=\"display: inline;\">\n"), 'HTML кликабельной команды не должен добавлять переносы внутрь pre-wrap');

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
    assertSameValue(200, $updatesPage['status'], 'Updates основного бота после очистки другого бота должны открываться');
    assertTrueValue(str_contains($updatesPage['body'], '100000001'), 'Очистка импортированного бота не должна удалить updates основного бота');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteMyCommands'), 200, true);
    assertSameValue(true, $json['result'], 'deleteMyCommands возвращает true');
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue([], $json['result'], 'deleteMyCommands очищает команды');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/sendInvoice', formBody([
        'chat_id' => '1001',
        'title' => 'Invoice',
        'description' => 'Unsupported invoice',
        'payload' => 'payload',
        'currency' => 'USD',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 501, false);
    assertSameValue(501, $json['error_code'], 'Неподдерживаемый Bot API метод возвращает 501');

    $groupToken = '345678:group-local-token';
    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/bots', json_encode([
        'bots' => [
            [
                'token' => $groupToken,
                'bot_id' => 345678,
                'username' => 'group_bot',
                'display_name' => 'Group Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['created'], 'Импорт group bot должен создавать отдельного бота');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/profiles', json_encode([
        'profiles' => [
            [
                'user_id' => 3001,
                'username' => 'group_alice',
                'first_name' => 'Alice',
                'last_name' => 'Sender',
                'chat_id' => -100300,
                'chat_type' => 'group',
                'language_code' => 'ru',
                'enabled' => true,
            ],
            [
                'user_id' => 3002,
                'username' => 'group_bob',
                'first_name' => 'Bob',
                'last_name' => 'Sender',
                'chat_id' => -100300,
                'chat_type' => 'group',
                'language_code' => 'ru',
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(2, $json['created'], 'Импорт group profiles должен разрешать общий chat_id');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '3',
        'bot_id' => '3',
        'text' => 'Group hello from Alice',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение первого отправителя группы должно сохраняться');

    $response = httpRequest('POST', $baseUrl . '/chat/send', formBody([
        'profile_id' => '4',
        'bot_id' => '3',
        'text' => 'Group hello from Bob',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Сообщение второго отправителя группы должно сохраняться');

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=3&bot_id=3');
    assertSameValue(200, $chat['status'], 'Group chat должен открываться');
    assertTrueValue(str_contains($chat['body'], 'Пользователь / отправитель'), 'UI должен явно выбирать отправителя');
    assertTrueValue(str_contains($chat['body'], 'group #-100300'), 'UI должен показывать тип и id group chat');
    assertTrueValue(str_contains($chat['body'], 'Group hello from Alice'), 'Group chat показывает сообщение первого отправителя');
    assertTrueValue(str_contains($chat['body'], 'Group hello from Bob'), 'Group chat показывает общую историю по chat_id');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $groupToken . '/getUpdates', json_encode([
        'limit' => 2,
        'allowed_updates' => ['message'],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(2, count($json['result']), 'Group bot должен получить два message update');
    assertSameValue(-100300, $json['result'][0]['message']['chat']['id'], 'Group update содержит общий chat_id');
    assertSameValue('group', $json['result'][0]['message']['chat']['type'], 'Group update содержит chat.type=group');
    assertSameValue('Chat -100300', $json['result'][0]['message']['chat']['title'], 'Group update содержит title чата');
    assertSameValue(3001, $json['result'][0]['message']['from']['id'], 'Первый group update содержит отправителя Alice');
    assertSameValue(3002, $json['result'][1]['message']['from']['id'], 'Второй group update содержит отправителя Bob');

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
    $response = httpRequest('POST', $baseUrl . '/chat/send', multipartBody([
        'profile_id' => '1',
        'bot_id' => '1',
        'message_type' => 'photo',
        'caption' => 'UI upload photo',
    ], $boundary, [
        'photo_file' => [
            'filename' => 'ui-photo.jpg',
            'content' => 'ui-photo-bytes',
            'content_type' => 'image/jpeg',
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

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/answerCallbackQuery', formBody([]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "callback_query_id" is required', $json['description'], 'answerCallbackQuery требует callback_query_id');
}


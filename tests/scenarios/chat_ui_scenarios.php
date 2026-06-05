<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runChatUiScenarios(array $context): void {
    extract($context);
    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Страница чата должна открываться');
    assertTrueValue(str_contains($chat['body'], '/private_en'), 'Чат показывает релевантные scoped language команды');
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
    assertTrueValue(str_contains($chat['body'], '<details class="panel chat-update-inspector">'), 'Последний update inspector должен быть спрятан в раскрывающийся блок');
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
    foreach (['video', 'animation', 'audio', 'voice', 'video_note', 'sticker', 'poll', 'venue', 'dice'] as $messageType) {
        assertDomXPathExists(
            $chatDom,
            '//details[contains(concat(" ", normalize-space(@class), " "), " chat-structured-inputs ")]//input[@type="hidden" and @name="message_type" and @value="' . $messageType . '"]',
            'DOM: вложения должны содержать форму ' . $messageType,
        );
    }
    assertDomXPathExists(
        $chatDom,
        '//details[contains(concat(" ", normalize-space(@class), " "), " chat-update-inspector ")]/summary[contains(normalize-space(.), "Последний Update")]',
        'DOM: последний update inspector должен быть details/summary',
    );

    foreach ([
        [
            'message_type' => 'dice',
            'emoji' => 'dice',
            'value' => '5',
        ],
        [
            'message_type' => 'venue',
            'latitude' => '43.1155',
            'longitude' => '131.8855',
            'title' => 'UI venue',
            'address' => 'UI address',
        ],
        [
            'message_type' => 'poll',
            'question' => 'UI poll',
            'options' => "One\nTwo",
        ],
    ] as $payload) {
        $response = httpRequest('POST', $baseUrl . '/chat/send', formBody(array_merge([
            'profile_id' => '1',
            'bot_id' => '1',
        ], $payload)), ['Content-Type: application/x-www-form-urlencoded']);
        assertSameValue(303, $response['status'], '/chat/send должен принимать UI message_type=' . $payload['message_type']);
    }

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Страница чата после UI structured messages должна открываться');
    assertTrueValue(str_contains($chat['body'], 'value 5'), 'UI dice должен сохраняться в истории сообщений');
    assertTrueValue(str_contains($chat['body'], 'UI venue'), 'UI venue должен сохраняться в истории сообщений');
    assertTrueValue(str_contains($chat['body'], 'UI poll'), 'UI poll должен сохраняться в истории сообщений');

    assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getUpdates?offset=200000000'), 200, true);

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

}

<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runFixturePackScenarios(array $context): void {
    extract($context);

    $screen = httpRequest('GET', $baseUrl . '/import-export');
    assertSameValue(200, $screen['status'], 'Экран import/export должен открываться перед fixture pack проверками');
    assertTrueValue(str_contains($screen['body'], '/export/fixture-pack'), 'Экран import/export должен содержать ссылку экспорта fixture pack');
    assertTrueValue(str_contains($screen['body'], '/import/fixture-pack'), 'Экран import/export должен содержать форму импорта fixture pack');

    $conflict = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/fixture-pack', json_encode([
        'kind' => 'telegram-emulator-fixture-pack',
        'version' => 2,
        'bots' => [
            [
                'token' => $token,
                'bot_id' => 123456,
                'username' => 'local_bot',
                'display_name' => 'Local Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
        'profiles' => [],
        'chats' => [],
        'bot_commands' => [],
        'media_manifest' => ['included' => false],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 409, false);
    assertTrueValue(str_contains((string) ($conflict['error'] ?? ''), 'Конфликт token'), 'Fixture pack должен отклонять конфликтующий bot token');

    $mediaJson = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/fixture-pack', json_encode([
        'kind' => 'telegram-emulator-fixture-pack',
        'version' => 2,
        'bots' => [],
        'profiles' => [],
        'chats' => [],
        'bot_commands' => [],
        'media_manifest' => ['included' => true],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 400, false);
    assertTrueValue(str_contains((string) ($mediaJson['error'] ?? ''), 'Бинарные media'), 'Fixture pack должен отклонять встроенные бинарные media в JSON');

    $fixtureToken = '777777:fixture-local-dev-token';
    $fixtureChatId = -100777;
    $invalidCommands = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/fixture-pack', json_encode([
        'kind' => 'telegram-emulator-fixture-pack',
        'version' => 2,
        'bots' => [
            [
                'token' => $fixtureToken,
                'bot_id' => 777777,
                'username' => 'fixture_bot',
                'display_name' => 'Fixture Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
        'profiles' => [],
        'chats' => [],
        'bot_commands' => [
            [
                'bot_token' => 'missing-token',
                'commands' => [
                    [
                        'command' => 'fixture',
                        'description' => 'Fixture command',
                    ],
                ],
            ],
        ],
        'media_manifest' => ['included' => false],
    ], JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 400, false);
    assertTrueValue(str_contains((string) ($invalidCommands['error'] ?? ''), 'Некорректный bot_token'), 'Fixture pack должен валидировать bot_commands section');

    $fixturePack = [
        'kind' => 'telegram-emulator-fixture-pack',
        'version' => 2,
        'bots' => [
            [
                'token' => $fixtureToken,
                'bot_id' => 777777,
                'username' => 'fixture_bot',
                'display_name' => 'Fixture Bot',
                'delivery_mode' => 'long_polling',
                'webhook_url' => null,
                'webhook_secret_token' => null,
                'enabled' => true,
            ],
        ],
        'profiles' => [
            [
                'user_id' => 7001,
                'username' => 'fixture_user',
                'first_name' => 'Fixture',
                'last_name' => 'User',
                'chat_id' => $fixtureChatId,
                'chat_type' => 'group',
                'language_code' => 'ru',
                'enabled' => true,
            ],
        ],
        'chats' => [
            [
                'chat_id' => $fixtureChatId,
                'type' => 'group',
                'title' => 'Fixture Group',
            ],
        ],
        'bot_commands' => [
            [
                'bot_token' => $fixtureToken,
                'commands' => [
                    [
                        'command' => 'fixture',
                        'description' => 'Fixture command',
                    ],
                ],
            ],
        ],
        'media_manifest' => [
            'included' => false,
        ],
    ];

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/import/fixture-pack', json_encode($fixturePack, JSON_THROW_ON_ERROR), ['Content-Type: application/json']), 200, true);
    assertSameValue(1, $json['created']['bots'], 'Fixture pack должен создать бота');
    assertSameValue(1, $json['created']['profiles'], 'Fixture pack должен создать профиль');
    assertSameValue(1, $json['created']['bot_commands'], 'Fixture pack должен импортировать группу bot_commands');
    assertSameValue(1, $json['created']['chats'], 'Fixture pack должен принять chat metadata');

    $export = assertJsonResponse(httpRequest('GET', $baseUrl . '/export/fixture-pack'), 200, true);
    assertSameValue('telegram-emulator-fixture-pack', $export['kind'], 'Fixture export должен возвращать kind');
    assertSameValue(2, $export['version'], 'Fixture export должен возвращать версию 2');
    assertTrueValue(isset($export['bots'], $export['profiles'], $export['chats'], $export['bot_commands'], $export['media_manifest']), 'Fixture export должен содержать все top-level секции');
    assertSameValue(false, $export['media_manifest']['included'], 'Fixture export не должен встраивать бинарные media в JSON');

    $exportedCommand = null;
    foreach ($export['bot_commands'] as $commandGroup) {
        if (($commandGroup['bot_token'] ?? '') === $fixtureToken) {
            $exportedCommand = $commandGroup['commands'][0] ?? null;
        }
    }

    assertSameValue(['command' => 'fixture', 'description' => 'Fixture command'], $exportedCommand, 'Fixture export должен сохранять импортированные bot_commands');

    $exportedChat = null;
    foreach ($export['chats'] as $chat) {
        if (($chat['chat_id'] ?? 0) === $fixtureChatId) {
            $exportedChat = $chat;
        }
    }

    assertSameValue('group', $exportedChat['type'] ?? null, 'Fixture export должен сохранять chat type');
    assertSameValue('Fixture Group', $exportedChat['title'] ?? null, 'Fixture export должен сохранять chat title из fixture pack');
}

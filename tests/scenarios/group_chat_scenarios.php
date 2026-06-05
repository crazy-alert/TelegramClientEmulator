<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $context
 */
function runGroupChatScenarios(array $context): void {
    extract($context);
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

    $response = httpRequest('POST', $baseUrl . '/profiles', formBody([
        'user_id' => '3003',
        'username' => 'group_charlie',
        'first_name' => 'Charlie',
        'last_name' => 'Sender',
        'chat_id' => '3003',
        'chat_type' => 'private',
        'language_code' => 'ru',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Подготовка private profile для добавления в группу должна проходить');

    $response = httpRequest('POST', $baseUrl . '/profiles', formBody([
        'user_id' => '3004',
        'username' => 'private_group_conflict',
        'first_name' => 'Private',
        'last_name' => 'Conflict',
        'chat_id' => '-100300',
        'chat_type' => 'private',
        'language_code' => 'ru',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(422, $response['status'], 'Private profile не должен занимать chat_id существующей группы');
    assertTrueValue(str_contains($response['body'], 'Private/channel chat_id'), 'Форма должна показывать конфликт private/group chat_id');

    $response = httpRequest('POST', $baseUrl . '/profiles', formBody([
        'user_id' => '3005',
        'username' => 'group_private_conflict',
        'first_name' => 'Group',
        'last_name' => 'Conflict',
        'chat_id' => '1001',
        'chat_type' => 'group',
        'language_code' => 'ru',
        'enabled' => '1',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(422, $response['status'], 'Group profile не должен занимать private chat_id');
    assertTrueValue(str_contains($response['body'], 'Private/channel chat_id'), 'Форма должна показывать конфликт group/private chat_id');

    $groups = httpRequest('GET', $baseUrl . '/group-chats');
    assertSameValue(200, $groups['status'], 'Список group/supergroup chats должен открываться');
    assertTrueValue(str_contains($groups['body'], 'Chat -100300'), 'Список групп должен показывать title');
    assertTrueValue(str_contains($groups['body'], '<code>-100300</code>'), 'Список групп должен показывать chat_id');
    assertTrueValue(str_contains($groups['body'], '<td>group</td>'), 'Список групп должен показывать type');

    $groupMembers = httpRequest('GET', $baseUrl . '/group-chats/-100300');
    assertSameValue(200, $groupMembers['status'], 'Экран участников группы должен открываться');
    assertTrueValue(str_contains($groupMembers['body'], 'group_alice'), 'Экран участников должен показывать Alice');
    assertTrueValue(str_contains($groupMembers['body'], 'group_bob'), 'Экран участников должен показывать Bob');
    assertTrueValue(str_contains($groupMembers['body'], 'group_charlie'), 'Private profile должен быть доступен для добавления');

    $response = httpRequest('POST', $baseUrl . '/group-chats/-100300/members', formBody([
        'profile_id' => '5',
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    assertSameValue(303, $response['status'], 'Добавление существующего profile в группу должно редиректить');

    $groupMembers = httpRequest('GET', $baseUrl . '/group-chats/-100300');
    assertSameValue(200, $groupMembers['status'], 'Экран участников после добавления должен открываться');
    assertTrueValue(str_contains($groupMembers['body'], 'group_charlie'), 'Добавленный участник должен отображаться в группе');

    $profileEditForm = httpRequest('GET', $baseUrl . '/profiles/5/edit');
    assertSameValue(200, $profileEditForm['status'], 'Профиль добавленного участника должен открываться');
    assertTrueValue(str_contains($profileEditForm['body'], 'value="-100300"'), 'Добавление в группу должно обновлять profile.chat_id');
    assertTrueValue(str_contains($profileEditForm['body'], '<option value="group" selected>group</option>'), 'Добавление в группу должно обновлять profile.chat_type');

    $response = httpRequest('POST', $baseUrl . '/group-chats/-100300/members/5/delete', null, []);
    assertSameValue(303, $response['status'], 'Удаление участника из группы должно редиректить');

    $profileEditForm = httpRequest('GET', $baseUrl . '/profiles/5/edit');
    assertSameValue(200, $profileEditForm['status'], 'Профиль удаленного участника должен открываться');
    assertTrueValue(str_contains($profileEditForm['body'], 'value="3003"'), 'Удаление из группы должно вернуть private chat_id=user_id');
    assertTrueValue(str_contains($profileEditForm['body'], '<option value="private" selected>private</option>'), 'Удаление из группы должно вернуть private chat_type');

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
}

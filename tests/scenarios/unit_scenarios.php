<?php

declare(strict_types=1);

function runUnitTests(): void {
    $generator = new \App\UpdateGenerator();
    $payload = $generator->generate(
        [
            'telegram_message_id' => 7,
            'text' => '/start hello',
        ],
        [
            'chat_id' => 1001,
            'user_id' => 1001,
            'chat_type' => 'private',
            'username' => 'dev_user',
            'first_name' => 'Dev',
            'last_name' => 'User',
            'language_code' => 'en',
        ],
        [
            'id' => 1,
            'username' => 'local_bot',
        ],
    );

    assertSameValue(0, $payload['update_id'], 'UpdateGenerator оставляет временный update_id до сохранения');
    assertSameValue(7, $payload['message']['message_id'], 'Message.message_id должен браться из сообщения');
    assertSameValue(1001, $payload['message']['chat']['id'], 'Chat.id должен браться из пользователя');
    assertSameValue('private', $payload['message']['chat']['type'], 'Chat.type должен браться из пользователя');
    assertSameValue(1001, $payload['message']['from']['id'], 'From.id должен браться из пользователя');
    assertSameValue(false, $payload['message']['from']['is_bot'], 'Пользовательский update не должен помечать from как бота');
    assertSameValue('/start hello', $payload['message']['text'], 'Message.text должен сохраняться без изменений');
    assertSameValue([
        [
            'offset' => 0,
            'length' => 6,
            'type' => 'bot_command',
        ],
    ], $payload['message']['entities'], 'Команда в начале текста должна давать entity bot_command');
}

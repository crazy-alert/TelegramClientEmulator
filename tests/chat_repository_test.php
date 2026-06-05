<?php

declare(strict_types=1);

use App\ChatRepository;
use App\Database;
use App\MigrationRunner;
use App\ProfileRepository;

require dirname(__DIR__) . '/src/Database.php';
require dirname(__DIR__) . '/src/MigrationRunner.php';
require dirname(__DIR__) . '/src/ProfileRepository.php';
require dirname(__DIR__) . '/src/ChatRepository.php';
require __DIR__ . '/support/test_helpers.php';

$runtime = sys_get_temp_dir() . '/telegram-emulator-chat-repository-test-' . getmypid();
mkdir($runtime, 0777, true);

try {
    $database = new Database($runtime);
    (new MigrationRunner($database->pdo(), dirname(__DIR__) . '/migrations'))->run();

    $profiles = new ProfileRepository($database->pdo());
    $chats = new ChatRepository($database->pdo());

    $profiles->create([
        'user_id' => 3001,
        'username' => 'group_alice',
        'first_name' => 'Alice',
        'last_name' => 'Sender',
        'chat_id' => -100300,
        'chat_type' => 'group',
        'language_code' => 'ru',
        'enabled' => '1',
    ]);
    $profiles->create([
        'user_id' => 3002,
        'username' => 'group_bob',
        'first_name' => 'Bob',
        'last_name' => 'Sender',
        'chat_id' => -100300,
        'chat_type' => 'group',
        'language_code' => 'ru',
        'enabled' => '1',
    ]);

    $chat = $chats->findByChatId(-100300);
    assertSameValue(-100300, (int) ($chat['chat_id'] ?? 0), 'Group chat должен быть отдельной сущностью по chat_id');
    assertSameValue('group', (string) ($chat['type'] ?? ''), 'Group chat должен сохранять type');
    assertSameValue('Chat -100300', (string) ($chat['title'] ?? ''), 'Group chat должен иметь стабильный title');

    $chats->updateGroupTitle(-100300, 'Local QA Group');
    $chat = $chats->findByChatId(-100300);
    assertSameValue('Local QA Group', (string) ($chat['title'] ?? ''), 'Group chat title должен редактироваться явно');

    $members = $chats->membersByChatId(-100300);
    assertSameValue(2, count($members), 'Group chat должен иметь двух участников');
    assertSameValue(3001, (int) $members[0]['user_id'], 'Первый участник group chat должен ссылаться на Alice profile');
    assertSameValue(3002, (int) $members[1]['user_id'], 'Второй участник group chat должен ссылаться на Bob profile');

    $profiles->update(2, [
        'user_id' => 3002,
        'username' => 'group_bob',
        'first_name' => 'Bob',
        'last_name' => 'Sender',
        'chat_id' => -100301,
        'chat_type' => 'supergroup',
        'language_code' => 'ru',
        'enabled' => '1',
    ]);

    $chat = $chats->findByChatId(-100300);
    assertSameValue('Local QA Group', (string) ($chat['title'] ?? ''), 'Profile sync не должен перезаписывать ручной title группы');

    assertSameValue(1, count($chats->membersByChatId(-100300)), 'При смене chat_id старый group chat теряет участника');
    assertSameValue(1, count($chats->membersByChatId(-100301)), 'При смене chat_id новый supergroup chat получает участника');

    echo "OK: Chat repository tests passed\n";
} finally {
    removeDirectory($runtime);
}

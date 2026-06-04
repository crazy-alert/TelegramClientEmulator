<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/UpdateRepository.php';
require dirname(__DIR__) . '/src/LongPollingService.php';

use App\LongPollingService;
use App\UpdateRepository;

final class LongPollingServiceTestFailure extends RuntimeException {
}

function assertLongPollingSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new LongPollingServiceTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

function longPollingPdo(): PDO {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        'CREATE TABLE updates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bot_id INTEGER NOT NULL,
            profile_id INTEGER NOT NULL,
            update_id INTEGER NOT NULL,
            payload TEXT NOT NULL,
            delivery_mode TEXT NOT NULL,
            queue_state TEXT NOT NULL DEFAULT "pending",
            confirmed_at TEXT
        )'
    );

    return $pdo;
}

/**
 * @param array<string, mixed>|string $payload
 */
function addLongPollingUpdate(PDO $pdo, int $botId, int $updateId, array|string $payload, string $state = 'pending'): void {
    $encodedPayload = is_array($payload)
        ? json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $payload;

    $statement = $pdo->prepare(
        'INSERT INTO updates (bot_id, profile_id, update_id, payload, delivery_mode, queue_state)
        VALUES (:bot_id, 1, :update_id, :payload, "long_polling", :queue_state)'
    );
    $statement->execute([
        'bot_id' => $botId,
        'update_id' => $updateId,
        'payload' => $encodedPayload,
        'queue_state' => $state,
    ]);
}

function longPollingService(PDO $pdo): LongPollingService {
    return new LongPollingService(new UpdateRepository($pdo));
}

$pdo = longPollingPdo();
addLongPollingUpdate($pdo, 1, 100, ['message' => ['text' => 'A']]);
addLongPollingUpdate($pdo, 1, 101, ['message' => ['text' => 'B']]);
addLongPollingUpdate($pdo, 1, 102, ['message' => ['text' => 'C']]);
assertLongPollingSame(
    [100, 101],
    array_column(longPollingService($pdo)->result(1, 0, 2, null), 'update_id'),
    'result должен соблюдать limit и порядок update_id',
);

$pdo = longPollingPdo();
addLongPollingUpdate($pdo, 1, 100, ['message' => ['text' => 'A']]);
addLongPollingUpdate($pdo, 1, 101, ['message' => ['text' => 'B']]);
addLongPollingUpdate($pdo, 1, 102, ['message' => ['text' => 'C']]);
assertLongPollingSame(
    [102],
    array_column(longPollingService($pdo)->result(1, 102, 100, null), 'update_id'),
    'positive offset должен вернуть updates начиная с offset',
);
assertLongPollingSame(
    [
        100 => 'confirmed',
        101 => 'confirmed',
        102 => 'pending',
    ],
    $pdo->query('SELECT update_id, queue_state FROM updates ORDER BY update_id')->fetchAll(PDO::FETCH_KEY_PAIR),
    'positive offset должен подтвердить pending updates до offset',
);

$pdo = longPollingPdo();
foreach ([100, 101, 102, 103, 104] as $updateId) {
    addLongPollingUpdate($pdo, 1, $updateId, ['message' => ['text' => (string) $updateId]]);
}
assertLongPollingSame(
    [103],
    array_column(longPollingService($pdo)->result(1, -2, 1, null), 'update_id'),
    'negative offset должен взять последние abs(offset) updates и затем применить limit',
);
assertLongPollingSame(
    [
        100 => 'confirmed',
        101 => 'confirmed',
        102 => 'confirmed',
        103 => 'pending',
        104 => 'pending',
    ],
    $pdo->query('SELECT update_id, queue_state FROM updates ORDER BY update_id')->fetchAll(PDO::FETCH_KEY_PAIR),
    'negative offset должен подтвердить updates до первого выбранного update_id',
);

$pdo = longPollingPdo();
addLongPollingUpdate($pdo, 1, 100, ['message' => ['text' => 'A']]);
addLongPollingUpdate($pdo, 1, 101, ['callback_query' => ['id' => 'cb']]);
addLongPollingUpdate($pdo, 1, 102, 'not-json');
assertLongPollingSame(
    [
        [
            'callback_query' => ['id' => 'cb'],
            'update_id' => 101,
        ],
    ],
    longPollingService($pdo)->result(1, 0, 100, ['callback_query']),
    'allowed_updates должен фильтровать типы update и пропускать malformed JSON',
);

$pdo = longPollingPdo();
for ($i = 1; $i <= 101; $i++) {
    addLongPollingUpdate($pdo, 1, 100 + $i, ['message' => ['text' => (string) $i]]);
}
assertLongPollingSame(
    100,
    count(longPollingService($pdo)->result(1, 0, 500, null)),
    'limit должен ограничиваться верхней границей 100',
);

echo "OK: long polling service tests passed\n";

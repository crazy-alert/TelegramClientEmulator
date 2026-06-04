<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/BotApiParams.php';

use App\BotApiParams;

final class BotApiParamsTestFailure extends RuntimeException {
}

function assertBotApiParamsSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new BotApiParamsTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

assertBotApiParamsSame(
    ['chat_id' => '2', 'text' => 'body'],
    BotApiParams::all(['chat_id' => '1'], ['chat_id' => '2', 'text' => 'body']),
    'Body params должны перекрывать query params',
);

assertBotApiParamsSame(42, BotApiParams::int('42', 0), 'int должен читать строковое число');
assertBotApiParamsSame(-7, BotApiParams::int('-7', 0), 'int должен читать отрицательное число');
assertBotApiParamsSame(5, BotApiParams::int('bad', 5), 'int должен возвращать default для мусора');

assertBotApiParamsSame(43.5, BotApiParams::float('43.5'), 'float должен читать строковое число');
assertBotApiParamsSame(null, BotApiParams::float(''), 'float должен возвращать null для пустой строки');

assertBotApiParamsSame(true, BotApiParams::truthy('true'), 'truthy должен понимать true');
assertBotApiParamsSame(true, BotApiParams::truthy('on'), 'truthy должен понимать on');
assertBotApiParamsSame(false, BotApiParams::truthy('false'), 'truthy должен отклонять false');

assertBotApiParamsSame(
    ['message', 'callback_query'],
    BotApiParams::allowedUpdates('["message","callback_query"]'),
    'allowedUpdates должен читать JSON array',
);
assertBotApiParamsSame(null, BotApiParams::allowedUpdates('bad'), 'allowedUpdates должен возвращать null для некорректного JSON');

assertBotApiParamsSame(
    [
        ['command' => 'start', 'description' => 'Start command'],
    ],
    BotApiParams::commands('[{"command":"/start","description":"Start command"}]'),
    'commands должен нормализовать slash-prefix',
);
assertBotApiParamsSame(null, BotApiParams::commands('[{"command":"bad-command","description":"Bad"}]'), 'commands должен валидировать command name');

assertBotApiParamsSame(
    [
        ['text' => 'A', 'voter_count' => 0],
        ['text' => 'B', 'voter_count' => 0],
    ],
    BotApiParams::pollOptions('["A","B"]'),
    'pollOptions должен читать JSON array строк',
);
assertBotApiParamsSame(null, BotApiParams::pollOptions('["A"]'), 'pollOptions должен требовать минимум два варианта');

echo "OK: bot api params tests passed\n";

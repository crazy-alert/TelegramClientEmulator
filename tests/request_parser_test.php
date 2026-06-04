<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/BotApiRequestParser.php';

use App\BotApiRequestParser;

final class RequestParserTestFailure extends RuntimeException {
}

/**
 * @param mixed $actual
 */
function assertParserSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RequestParserTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

/**
 * @param array<string, string> $fields
 * @param array<string, string> $files
 */
function parserMultipartBody(array $fields, array $files, string $boundary): string {
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
        $body .= $value . "\r\n";
    }

    foreach ($files as $name => $fileName) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $fileName . '"' . "\r\n";
        $body .= 'Content-Type: application/octet-stream' . "\r\n\r\n";
        $body .= 'file-bytes' . "\r\n";
    }

    $body .= '--' . $boundary . "--\r\n";

    return $body;
}

$parser = new BotApiRequestParser();

assertParserSame(
    ['chat_id' => 1001, 'text' => 'Привет'],
    $parser->parse('POST', '{"chat_id":1001,"text":"Привет"}', 'application/json'),
    'JSON body должен парситься в массив',
);

assertParserSame(
    ['chat_id' => '1001', 'text' => 'hello world'],
    $parser->parse('POST', 'chat_id=1001&text=hello+world', 'application/x-www-form-urlencoded'),
    'Form-urlencoded body должен парситься в массив строк',
);

$boundary = 'telegram-emulator-test-boundary';
assertParserSame(
    ['chat_id' => '1001', 'caption' => 'Документ'],
    $parser->parse(
        'POST',
        parserMultipartBody(['chat_id' => '1001', 'caption' => 'Документ'], ['document' => 'report.txt'], $boundary),
        'multipart/form-data; boundary="' . $boundary . '"',
    ),
    'Multipart parser должен возвращать только текстовые поля и игнорировать файлы',
);

assertParserSame(
    null,
    $parser->parse('POST', '', 'application/json'),
    'Пустое тело не должно менять $_POST',
);

assertParserSame(
    null,
    $parser->parse('POST', '{"chat_id":', 'application/json'),
    'Malformed JSON не должен менять $_POST',
);

echo "OK: request parser tests passed\n";

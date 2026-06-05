<?php

declare(strict_types=1);

final class BotApiSurfaceCatalogTestFailure extends RuntimeException {
}

function assertCatalogTrue(bool $condition, string $message): void {
    if (!$condition) {
        throw new BotApiSurfaceCatalogTestFailure($message);
    }
}

function assertCatalogSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new BotApiSurfaceCatalogTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

$catalogPath = dirname(__DIR__) . '/docs/bot-api-surface.json';
$catalog = json_decode((string) file_get_contents($catalogPath), true);
assertCatalogTrue(is_array($catalog), 'Каталог Bot API surface должен быть валидным JSON object');
assertCatalogSame(1, $catalog['schema_version'] ?? null, 'Каталог должен иметь schema_version=1');
assertCatalogTrue(is_array($catalog['unsupported_methods'] ?? null), 'Каталог должен описывать unsupported_methods');
assertCatalogSame(501, $catalog['unsupported_methods']['http_status'] ?? null, 'Unsupported methods должны возвращать HTTP 501');

$methods = $catalog['methods'] ?? null;
assertCatalogTrue(is_array($methods) && array_is_list($methods), 'Каталог должен содержать list methods');

$byName = [];
foreach ($methods as $method) {
    assertCatalogTrue(is_array($method), 'Каждый method должен быть object');
    foreach (['name', 'http_methods', 'required_params', 'optional_params', 'content_types', 'media_upload', 'limitations', 'test_status'] as $field) {
        assertCatalogTrue(array_key_exists($field, $method), 'Method должен содержать поле ' . $field);
    }

    $name = (string) $method['name'];
    assertCatalogTrue($name !== '', 'Method.name не должен быть пустым');
    assertCatalogTrue(!isset($byName[$name]), 'Method.name должен быть уникальным: ' . $name);
    assertCatalogTrue(is_array($method['http_methods']) && $method['http_methods'] !== [], 'Method должен иметь HTTP verbs: ' . $name);
    assertCatalogTrue(is_array($method['content_types']) && $method['content_types'] !== [], 'Method должен иметь content_types: ' . $name);
    assertCatalogTrue(is_bool($method['media_upload']), 'Method.media_upload должен быть boolean: ' . $name);
    assertCatalogTrue(is_array($method['limitations']), 'Method.limitations должен быть list: ' . $name);
    $byName[$name] = $method;
}

$controllerSource = (string) file_get_contents(dirname(__DIR__) . '/src/BotApiController.php');
preg_match_all("~preg_match\\('#\\^/bot\\(\\[\\^/\\]\\+\\)/([A-Za-z0-9]+)\\$#i'~", $controllerSource, $matches);
$routedMethods = array_values(array_unique($matches[1] ?? []));
sort($routedMethods);
$catalogMethods = array_keys($byName);
sort($catalogMethods);
assertCatalogSame($routedMethods, $catalogMethods, 'Каталог должен совпадать с именами Bot API routes в BotApiController');

foreach (['getUpdates', 'sendMessage', 'sendPhoto', 'sendDocument', 'setMyCommands', 'getMyCommands', 'answerCallbackQuery'] as $requiredMethod) {
    assertCatalogTrue(isset($byName[$requiredMethod]), 'Каталог должен содержать ' . $requiredMethod);
}

assertCatalogTrue(in_array('timeout', $byName['getUpdates']['optional_params'], true), 'getUpdates должен описывать timeout');
assertCatalogSame(true, $byName['sendPhoto']['media_upload'], 'sendPhoto должен иметь media_upload=true');
assertCatalogTrue(in_array('multipart/form-data', $byName['sendDocument']['content_types'], true), 'sendDocument должен описывать multipart/form-data');
assertCatalogTrue(in_array('scope', $byName['setMyCommands']['optional_params'], true), 'setMyCommands должен описывать scope');
assertCatalogTrue(in_array('language_code', $byName['getMyCommands']['optional_params'], true), 'getMyCommands должен описывать language_code');

echo "OK: Bot API surface catalog tests passed\n";

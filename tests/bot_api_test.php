<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/UpdateGenerator.php';

use App\UpdateGenerator;

final class TestFailure extends RuntimeException {
}

/**
 * @param mixed $actual
 */
function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new TestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

function assertTrueValue(bool $condition, string $message): void {
    if (!$condition) {
        throw new TestFailure($message);
    }
}

/**
 * @param array<string, mixed> $array
 */
function assertArrayHasKeyValue(string $key, array $array, string $message): void {
    if (!array_key_exists($key, $array)) {
        throw new TestFailure($message . "\nНет ключа: " . $key);
    }
}

/**
 * @return array{status: int, headers: list<string>, body: string, json: array<string, mixed>|null}
 */
function httpRequest(string $method, string $url, ?string $body = null, array $headers = []): array {
    $httpHeaders = $headers;
    if ($body !== null && !array_key_exists('Content-Length', headerMap($httpHeaders))) {
        $httpHeaders[] = 'Content-Length: ' . strlen($body);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $httpHeaders),
            'content' => $body ?? '',
            'ignore_errors' => true,
            'max_redirects' => 0,
            'timeout' => 5,
        ],
    ]);

    $responseBody = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
            $status = (int) $matches[1];
            break;
        }
    }

    $rawBody = $responseBody === false ? '' : $responseBody;
    $decoded = json_decode($rawBody, true);

    return [
        'status' => $status,
        'headers' => $responseHeaders,
        'body' => $rawBody,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

/**
 * @param list<string> $headers
 * @return array<string, string>
 */
function headerMap(array $headers): array {
    $map = [];
    foreach ($headers as $header) {
        [$name, $value] = array_pad(explode(':', $header, 2), 2, '');
        if ($name !== '') {
            $map[$name] = trim($value);
        }
    }

    return $map;
}

/**
 * @param array<string, string> $data
 */
function formBody(array $data): string {
    return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
}

/**
 * @param array<string, string> $data
 */
function multipartBody(array $data, string $boundary): string {
    $body = '';
    foreach ($data as $name => $value) {
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
        $body .= $value . "\r\n";
    }
    $body .= '--' . $boundary . "--\r\n";

    return $body;
}

/**
 * @return array<string, mixed>
 */
function assertJsonResponse(array $response, int $status, bool $ok): array {
    assertSameValue($status, $response['status'], 'HTTP-статус не совпал');
    assertTrueValue(is_array($response['json']), 'Ответ должен быть JSON: ' . $response['body']);
    assertSameValue($ok, $response['json']['ok'] ?? null, 'Поле ok не совпало');

    return $response['json'];
}

function waitForServer(string $baseUrl): void {
    $deadline = microtime(true) + 5;
    do {
        $response = @httpRequest('GET', $baseUrl . '/health');
        if (($response['status'] ?? 0) === 200) {
            return;
        }
        usleep(100_000);
    } while (microtime(true) < $deadline);

    throw new TestFailure('Тестовый HTTP server не запустился');
}

function removeDirectory(string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

/**
 * @return resource
 */
function startWebhookReceiver(string $runtime, int $port): mixed {
    $receiverRoot = $runtime . '/receiver';
    mkdir($receiverRoot, 0777, true);
    file_put_contents($receiverRoot . '/receiver.php', <<<'PHP'
<?php

$status = (int) ($_GET['status'] ?? 202);
$body = file_get_contents('php://input') ?: '';
$logFile = getenv('WEBHOOK_RECEIVER_LOG') ?: sys_get_temp_dir() . '/telegram-emulator-webhook.log';
file_put_contents($logFile, json_encode([
    'status' => $status,
    'headers' => [
        'secret_token' => $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? null,
    ],
    'body' => $body,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
http_response_code($status);
header('Content-Type: application/json');
echo json_encode(['ok' => $status >= 200 && $status < 300]);
PHP);

    $command = PHP_BINARY . ' -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($receiverRoot)
        . ' ' . escapeshellarg($receiverRoot . '/receiver.php');

    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $receiverRoot, [
        'WEBHOOK_RECEIVER_LOG' => $runtime . '/receiver.log',
    ]);

    if (!is_resource($process)) {
        throw new TestFailure('Не удалось запустить webhook receiver');
    }

    $deadline = microtime(true) + 5;
    do {
        $response = @httpRequest('GET', 'http://127.0.0.1:' . $port . '/receiver.php?status=204');
        if (($response['status'] ?? 0) === 204) {
            return $process;
        }
        usleep(100_000);
    } while (microtime(true) < $deadline);

    proc_terminate($process);
    proc_close($process);
    throw new TestFailure('Webhook receiver не запустился');
}

function runUnitTests(): void {
    $generator = new UpdateGenerator();
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

function runHttpTests(string $baseUrl, int $receiverPort): void {
    $token = '123456:local-dev-token-test';

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
    assertTrueValue(str_contains($chat['body'], 'hx-get="/chat/fragment?profile_id=1&amp;bot_id=1"'), 'Чат должен polling-обновлять выбранную пару');
    assertTrueValue(str_contains($chat['body'], 'hx-trigger="every 3s"'), 'Чат должен периодически обновлять фрагмент');
    assertTrueValue(str_contains($chat['body'], 'queue_state</th>'), 'Inspector должен показывать update');
    assertTrueValue(str_contains($chat['body'], '>failed<'), 'Failed webhook должен оставить update в состоянии failed');
    assertTrueValue(str_contains($chat['body'], '/updates/1/resend'), 'Для failed update должна быть кнопка resend');

    $fragment = httpRequest('GET', $baseUrl . '/chat/fragment?profile_id=1&bot_id=1');
    assertSameValue(200, $fragment['status'], 'HTMX-фрагмент чата должен открываться');
    assertTrueValue(!str_contains($fragment['body'], '<h1>Чат</h1>'), 'HTMX-фрагмент не должен возвращать полную страницу чата');
    assertTrueValue(str_contains($fragment['body'], 'queue_state</th>'), 'HTMX-фрагмент должен сохранять inspector');
    assertTrueValue(str_contains($fragment['body'], '/updates/1/resend'), 'HTMX-фрагмент должен сохранять кнопку resend');

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

    $chat = httpRequest('GET', $baseUrl . '/chat?profile_id=1&bot_id=1');
    assertSameValue(200, $chat['status'], 'Страница чата должна открываться');
    assertTrueValue(str_contains($chat['body'], '/start'), 'Чат показывает сохраненные команды');
    assertTrueValue(str_contains($chat['body'], 'Inline action'), 'Чат показывает inline keyboard');
    assertTrueValue(str_contains($chat['body'], 'Reply A'), 'Чат показывает reply keyboard');

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

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/deleteMyCommands'), 200, true);
    assertSameValue(true, $json['result'], 'deleteMyCommands возвращает true');
    $json = assertJsonResponse(httpRequest('GET', $baseUrl . '/bot' . $token . '/getMyCommands'), 200, true);
    assertSameValue([], $json['result'], 'deleteMyCommands очищает команды');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/editMessageText', formBody([
        'chat_id' => '1001',
        'message_id' => '1',
        'text' => 'Edited',
    ]), ['Content-Type: application/x-www-form-urlencoded']), 501, false);
    assertSameValue(501, $json['error_code'], 'Неподдерживаемый Bot API метод возвращает 501');

    $json = assertJsonResponse(httpRequest('POST', $baseUrl . '/bot' . $token . '/answerCallbackQuery', formBody([]), ['Content-Type: application/x-www-form-urlencoded']), 400, false);
    assertSameValue('Bad Request: parameter "callback_query_id" is required', $json['description'], 'answerCallbackQuery требует callback_query_id');
}

function main(): int {
    $root = dirname(__DIR__);
    $runtime = sys_get_temp_dir() . '/telegram-emulator-tests-' . getmypid();
    $dataDir = $runtime . '/data';
    $logDir = $runtime . '/logs';
    mkdir($dataDir, 0777, true);
    mkdir($logDir, 0777, true);

    $port = 18082;
    $receiverPort = 18083;
    $baseUrl = 'http://127.0.0.1:' . $port;
    $command = PHP_BINARY . ' -c ' . escapeshellarg($root . '/php.ini')
        . ' -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($root . '/public')
        . ' ' . escapeshellarg($root . '/public/index.php');

    $environment = array_merge($_ENV, [
        'DATA_DIR' => $dataDir,
        'LOG_DIR' => $logDir,
        'APP_HOST' => '127.0.0.1',
        'APP_PORT' => (string) $port,
    ]);

    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root, $environment);

    if (!is_resource($process)) {
        throw new TestFailure('Не удалось запустить тестовый HTTP server');
    }

    $receiverProcess = startWebhookReceiver($runtime, $receiverPort);

    try {
        runUnitTests();
        waitForServer($baseUrl);
        runHttpTests($baseUrl, $receiverPort);
    } finally {
        proc_terminate($receiverProcess);
        proc_close($receiverProcess);
        proc_terminate($process);
        proc_close($process);
        removeDirectory($runtime);
    }

    echo "OK: Bot API tests passed\n";

    return 0;
}

try {
    exit(main());
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

<?php

declare(strict_types=1);

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

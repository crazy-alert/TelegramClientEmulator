<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/ReplyMarkup.php';
require dirname(__DIR__) . '/src/UpdateGenerator.php';
require __DIR__ . '/support/test_helpers.php';
require __DIR__ . '/scenarios/unit_scenarios.php';
require __DIR__ . '/scenarios/http_scenarios.php';

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
        'LONG_POLLING_MAX_TIMEOUT_SECONDS' => '1',
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

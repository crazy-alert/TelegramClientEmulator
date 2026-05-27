<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Пишет HTTP request/response события в JSONL-файлы.
 */
final readonly class HttpLogger {

    private const RETENTION_DAYS = 5;
    private const BODY_LIMIT_BYTES = 65536;

    public function __construct(private string $logDir) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function log(array $data): void {
        $this->ensureLogDir();
        $this->rotate();

        $path = $this->logDir . DIRECTORY_SEPARATOR . 'http-' . date('Y-m-d') . '.jsonl';
        $line = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line !== false) {
            file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, mixed>
     */
    public function requestContext(array $server, string $rawBody): array {
        return [
            'timestamp' => date('c'),
            'request' => [
                'method' => $server['REQUEST_METHOD'] ?? 'GET',
                'uri' => $server['REQUEST_URI'] ?? '/',
                'path' => parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/',
                'query' => $server['QUERY_STRING'] ?? '',
                'remote_addr' => $server['REMOTE_ADDR'] ?? null,
                'user_agent' => $server['HTTP_USER_AGENT'] ?? null,
                'headers' => $this->requestHeaders($server),
                'body' => $this->truncate($rawBody),
            ],
        ];
    }

    /**
     * @return array{class: string, message: string, file: string, line: int}
     */
    public function errorContext(Throwable $exception): array {
        return [
            'class' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];
    }

    private function ensureLogDir(): void {
        if (!is_dir($this->logDir) && !mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
            throw new \RuntimeException('Не удалось создать директорию логов: ' . $this->logDir);
        }
    }

    private function rotate(): void {
        $threshold = time() - self::RETENTION_DAYS * 86400;
        foreach (glob($this->logDir . DIRECTORY_SEPARATOR . 'http-*.jsonl') ?: [] as $path) {
            if (is_file($path) && filemtime($path) !== false && filemtime($path) < $threshold) {
                unlink($path);
            }
        }
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private function requestHeaders(array $server): array {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            if (str_starts_with((string) $key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string) $key, 5)))));
                $headers[$name] = (string) $value;
            }
        }

        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            if (isset($server[$key]) && (is_string($server[$key]) || is_numeric($server[$key]))) {
                $headers[$name] = (string) $server[$key];
            }
        }

        return $headers;
    }

    private function truncate(string $value): string {
        if (strlen($value) <= self::BODY_LIMIT_BYTES) {
            return $value;
        }

        return substr($value, 0, self::BODY_LIMIT_BYTES) . '... [truncated]';
    }
}

<?php

declare(strict_types=1);

namespace App;

/**
 * Read-only доступ к HTTP JSONL-логам для request/response inspector.
 */
final readonly class HttpLogRepository {

    public function __construct(private string $logDir) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function botApiEvents(
        ?string $tokenFilter = null,
        ?string $methodFilter = null,
        ?int $responseStatusFilter = null,
        bool $onlyOkFalse = false,
        int $limit = 100,
    ): array {
        $events = [];
        $tokenFilter = trim((string) $tokenFilter);
        $methodFilter = trim((string) $methodFilter);

        foreach ($this->logFilesNewestFirst() as $path) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $decoded = json_decode($lines[$i], true);
                if (!is_array($decoded)) {
                    continue;
                }

                $event = $this->botApiEvent($decoded);
                if ($event === null) {
                    continue;
                }

                if ($tokenFilter !== '' && (string) $event['token_raw'] !== $tokenFilter) {
                    continue;
                }

                if ($methodFilter !== '' && strcasecmp((string) $event['bot_api_method'], $methodFilter) !== 0) {
                    continue;
                }

                if ($responseStatusFilter !== null && (int) $event['response_status'] !== $responseStatusFilter) {
                    continue;
                }

                if ($onlyOkFalse && ($event['response_ok'] ?? null) !== false) {
                    continue;
                }

                unset($event['token_raw']);
                $events[] = $event;

                if (count($events) >= max(1, min(500, $limit))) {
                    return $events;
                }
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    private function logFilesNewestFirst(): array {
        $files = glob($this->logDir . DIRECTORY_SEPARATOR . 'http-*.jsonl') ?: [];
        usort($files, static fn(string $left, string $right): int => strcmp($right, $left));

        return array_values(array_filter($files, 'is_file'));
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null
     */
    private function botApiEvent(array $event): ?array {
        $request = is_array($event['request'] ?? null) ? $event['request'] : [];
        $response = is_array($event['response'] ?? null) ? $event['response'] : [];
        $path = (string) ($request['path'] ?? '');

        if (preg_match('#^/bot([^/]+)/([^/?]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $token = $matches[1];
        $method = $matches[2];

        $requestHeaders = $this->maskedHeaders($request['headers'] ?? []);
        $requestBody = $this->maskSecrets((string) ($request['body'] ?? ''));
        $responseHeaders = $this->maskedHeaders($response['headers'] ?? []);
        $responseBody = $this->maskSecrets((string) ($response['body'] ?? ''));
        $responseStatus = (int) ($response['status'] ?? 0);
        $responseDecoded = json_decode($responseBody, true);

        return [
            'timestamp' => (string) ($event['timestamp'] ?? ''),
            'duration_ms' => (int) ($event['duration_ms'] ?? 0),
            'request_method' => (string) ($request['method'] ?? ''),
            'bot_token' => $this->maskToken($token),
            'token_raw' => $token,
            'bot_api_method' => $method,
            'uri' => $this->maskSecrets((string) ($request['uri'] ?? $path)),
            'query' => $this->maskSecrets((string) ($request['query'] ?? '')),
            'request_headers' => $requestHeaders,
            'request_body' => $requestBody,
            'request_body_pretty' => $this->prettyJson($requestBody),
            'curl' => $this->curlCommand((string) ($request['method'] ?? 'GET'), $this->maskSecrets((string) ($request['uri'] ?? $path)), $requestHeaders, $requestBody),
            'response_status' => $responseStatus,
            'response_ok' => is_array($responseDecoded) && array_key_exists('ok', $responseDecoded) ? (bool) $responseDecoded['ok'] : null,
            'response_headers' => $responseHeaders,
            'response_body' => $responseBody,
            'response_body_pretty' => $this->prettyJson($responseBody),
            'error' => is_array($event['error'] ?? null) ? $event['error'] : null,
        ];
    }

    /**
     * @param mixed $headers
     * @return array<string, string>
     */
    private function maskedHeaders(mixed $headers): array {
        if (!is_array($headers)) {
            return [];
        }

        $masked = [];
        foreach ($headers as $name => $value) {
            $name = (string) $name;
            $masked[$name] = str_contains(strtolower($name), 'token')
                ? '***'
                : $this->maskSecrets((string) $value);
        }

        return $masked;
    }

    private function maskSecrets(string $value): string {
        $masked = preg_replace_callback(
            '/(\d{5,10}):[a-zA-Z0-9_.+-]{15,}/',
            fn(array $matches): string => $matches[1] . ':***',
            $value,
        ) ?? $value;

        $masked = preg_replace('/(X-Telegram-Bot-Api-Secret-Token:\s*)[^\r\n",]+/i', '$1***', $masked) ?? $masked;

        return preg_replace('/("(?:webhook_)?secret_token"\s*:\s*")[^"]+(")/i', '$1***$2', $masked) ?? $masked;
    }

    private function maskToken(string $token): string {
        return $this->maskSecrets($token);
    }

    private function prettyJson(string $value): string {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $value;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: $value;
    }

    /**
     * @param array<string, string> $headers
     */
    private function curlCommand(string $method, string $uri, array $headers, string $body): string {
        $parts = [
            'curl',
            '-X',
            $this->shellQuote(strtoupper($method)),
            $this->shellQuote($uri),
        ];

        foreach ($headers as $name => $value) {
            $parts[] = '-H';
            $parts[] = $this->shellQuote($name . ': ' . $value);
        }

        if ($body !== '') {
            $parts[] = '--data';
            $parts[] = $this->shellQuote($body);
        }

        return implode(' ', $parts);
    }

    private function shellQuote(string $value): string {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}

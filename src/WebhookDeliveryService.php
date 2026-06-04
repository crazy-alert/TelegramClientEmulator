<?php

declare(strict_types=1);

namespace App;

use Throwable;

/**
 * Отвечает за одну попытку webhook-доставки update и сохранение результата.
 */
final readonly class WebhookDeliveryService {

    public function __construct(
        private DeliveryAttemptRepository $deliveryAttempts,
        private UpdateRepository $updates,
    ) {
    }

    /**
     * @param array{id: int, update_id: int, payload: string} $update
     * @param array<string, mixed> $bot
     */
    public function deliver(array $update, array $bot, int $timeoutSeconds): void {
        $url = (string) $bot['webhook_url'];
        $headers = [
            'Content-Type: application/json',
        ];
        $secretToken = trim((string) ($bot['webhook_secret_token'] ?? ''));
        if ($secretToken !== '') {
            $headers[] = 'X-Telegram-Bot-Api-Secret-Token: ' . $secretToken;
        }

        $startedAt = microtime(true);
        $responseBody = null;
        $responseHeaders = [];
        $responseStatus = null;
        $error = null;

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $update['payload'],
                'ignore_errors' => true,
                'timeout' => $timeoutSeconds,
            ],
        ]);

        try {
            $response = file_get_contents($url, false, $context);
            $responseHeaders = $http_response_header ?? [];
            $responseStatus = $this->httpStatusFromHeaders($responseHeaders);
            $responseBody = $response === false ? null : $response;

            if ($response === false) {
                $error = 'Webhook request failed';
            } elseif ($responseStatus === null || $responseStatus < 200 || $responseStatus >= 300) {
                $error = 'Webhook returned HTTP ' . ($responseStatus ?? 'unknown');
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $delivered = $error === null;

        $this->deliveryAttempts->create([
            'update_row_id' => $update['id'],
            'bot_id' => (int) $bot['id'],
            'webhook_url' => $url,
            'request_headers' => json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_body' => $update['payload'],
            'response_status' => $responseStatus,
            'response_headers' => json_encode($responseHeaders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response_body' => $responseBody,
            'duration_ms' => $durationMs,
            'error' => $error,
        ]);
        $this->updates->markWebhookDelivery((int) $update['id'], $delivered);
    }

    /**
     * @param list<string> $headers
     */
    private function httpStatusFromHeaders(array $headers): ?int {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}

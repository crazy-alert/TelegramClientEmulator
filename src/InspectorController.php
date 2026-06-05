<?php

declare(strict_types=1);

namespace App;

use Closure;

/**
 * UI-маршруты inspector/update/delivery среза.
 */
final readonly class InspectorController {

    public function __construct(
        private BotRepository $bots,
        private ProfileRepository $profiles,
        private UpdateRepository $updates,
        private DeliveryAttemptRepository $deliveryAttempts,
        private HttpLogRepository $httpLogs,
        private WebhookDeliveryService $webhookDelivery,
        private View $view,
        private Closure $webhookTimeoutSeconds,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if ($method === 'POST' && preg_match('#^/updates/(\d+)/resend$#', $path, $matches) === 1) {
            $this->resendWebhookUpdate((int) $matches[1]);
            return true;
        }

        if ($method === 'GET' && $path === '/delivery-attempts') {
            $this->deliveryAttemptsIndex();
            return true;
        }

        if ($method === 'GET' && $path === '/updates') {
            $this->updatesIndex();
            return true;
        }

        if ($method === 'POST' && $path === '/updates/clear') {
            $this->updatesClear();
            return true;
        }

        if ($method === 'GET' && $path === '/request-inspector') {
            $this->requestInspector();
            return true;
        }

        return false;
    }

    private function resendWebhookUpdate(int $updateRowId): void {
        $update = $this->updates->find($updateRowId);

        if ($update === null) {
            Response::json([
                'ok' => false,
                'error' => 'Update не найден',
            ], 404);
            return;
        }

        if ((string) ($update['queue_state'] ?? '') !== 'failed') {
            Response::json([
                'ok' => false,
                'error' => 'Повторная отправка доступна только для failed updates',
            ], 400);
            return;
        }

        $bot = $this->bots->find((int) $update['bot_id']);
        if ($bot === null || trim((string) ($bot['webhook_url'] ?? '')) === '') {
            Response::json([
                'ok' => false,
                'error' => 'У бота не настроен webhook URL',
            ], 400);
            return;
        }

        $this->webhookDelivery->deliver([
            'id' => (int) $update['id'],
            'update_id' => (int) $update['update_id'],
            'payload' => (string) $update['payload'],
        ], $bot, ($this->webhookTimeoutSeconds)());

        Response::redirect('/chat?profile_id=' . (int) $update['profile_id'] . '&bot_id=' . (int) $bot['id']);
    }

    private function deliveryAttemptsIndex(): void {
        $botId = $this->intParam($_GET['bot_id'] ?? 0, 0);
        $updateId = $this->intParam($_GET['update_id'] ?? 0, 0);

        $this->render('delivery-attempts/index', [
            'title' => 'Webhook delivery attempts',
            'attempts' => $this->deliveryAttempts->allWithContext(
                $botId > 0 ? $botId : null,
                $updateId > 0 ? $updateId : null,
            ),
            'selectedBotId' => $botId,
            'selectedUpdateId' => $updateId,
        ]);
    }

    private function updatesIndex(): void {
        $botId = $this->intParam($_GET['bot_id'] ?? 0, 0);
        $profileId = $this->intParam($_GET['profile_id'] ?? 0, 0);
        $updateId = $this->intParam($_GET['update_id'] ?? 0, 0);
        $queueState = (string) ($_GET['queue_state'] ?? '');
        $queueState = in_array($queueState, ['pending', 'delivered', 'confirmed', 'failed'], true)
            ? $queueState
            : '';

        $this->render('updates/index', [
            'title' => 'Updates',
            'updates' => $this->updates->allWithContext(
                $botId > 0 ? $botId : null,
                $profileId > 0 ? $profileId : null,
                $updateId > 0 ? $updateId : null,
                $queueState === '' ? null : $queueState,
            ),
            'selectedBotId' => $botId,
            'selectedProfileId' => $profileId,
            'selectedUpdateId' => $updateId,
            'selectedQueueState' => $queueState,
        ]);
    }

    private function updatesClear(): void {
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $bot = $this->bots->find($botId);

        if ($bot === null || (string) ($_POST['confirm_clear'] ?? '') !== '1') {
            Response::json([
                'ok' => false,
                'error' => 'Для очистки updates нужно выбрать бота и подтвердить действие',
            ], 400);
            return;
        }

        $this->updates->deletePendingAndConfirmedByBot((int) $bot['id']);
        Response::redirect('/updates?bot_id=' . (int) $bot['id']);
    }

    private function requestInspector(): void {
        $tokenFilter = trim((string) ($_GET['token'] ?? ''));
        $methodFilter = trim((string) ($_GET['method'] ?? ''));

        $this->render('request-inspector/index', [
            'title' => 'Request inspector',
            'botApiEvents' => $this->httpLogs->botApiEvents(
                $tokenFilter === '' ? null : $tokenFilter,
                $methodFilter === '' ? null : $methodFilter,
                100,
            ),
            'webhookAttempts' => array_map(
                fn(array $attempt): array => $this->maskedWebhookAttempt($attempt),
                $this->deliveryAttempts->allWithContext(null, null, 50),
            ),
            'selectedMethod' => $methodFilter,
            'hasTokenFilter' => $tokenFilter !== '',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->render($template, $data);
    }

    /**
     * @param array<string, mixed> $attempt
     * @return array<string, mixed>
     */
    private function maskedWebhookAttempt(array $attempt): array {
        foreach (['webhook_url', 'request_headers', 'request_body', 'response_headers', 'response_body', 'error'] as $field) {
            if (array_key_exists($field, $attempt) && $attempt[$field] !== null) {
                $attempt[$field] = $this->maskSecrets((string) $attempt[$field]);
            }
        }

        return $attempt;
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

    private function intParam(mixed $value, int $default): int {
        if (is_int($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if (preg_match('/^-?\d+$/', $value) !== 1) {
            return $default;
        }

        return (int) $value;
    }

}

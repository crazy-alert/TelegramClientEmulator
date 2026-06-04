<?php

declare(strict_types=1);

namespace App;

use Closure;

/**
 * Обрабатывает UI-маршруты локального чата.
 */
final readonly class ChatController {

    public function __construct(
        private Database $database,
        private BotRepository $bots,
        private BotCommandRepository $botCommands,
        private ProfileRepository $profiles,
        private MessageRepository $messages,
        private UpdateRepository $updates,
        private DeliveryAttemptRepository $deliveryAttempts,
        private UpdateGenerator $updateGenerator,
        private WebhookDeliveryService $webhookDelivery,
        private View $view,
        private Closure $webhookTimeoutSeconds,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if ($method === 'GET' && $path === '/chat') {
            $this->index();
            return true;
        }

        if ($method === 'GET' && $path === '/chat/fragment') {
            $this->fragment();
            return true;
        }

        if ($method === 'POST' && $path === '/chat/send') {
            $this->send();
            return true;
        }

        if ($method === 'POST' && $path === '/chat/callback') {
            $this->callback();
            return true;
        }

        if ($method === 'POST' && $path === '/chat/clear') {
            $this->clear();
            return true;
        }

        return false;
    }

    private function index(): void {
        $this->render('chat/index', $this->viewData());
    }

    private function fragment(): void {
        $data = $this->viewData();

        $data['chatFragment'] = true;
        $this->renderPartial('chat/index', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(): array {
        $profile = $this->selectedUser();
        $bot = $this->selectedBot();

        $messages = [];
        $latestUpdate = null;
        $latestDeliveryAttempt = null;
        $pendingUpdateCount = 0;
        $botCommands = [];

        if ($profile !== null && $bot !== null) {
            $messages = $this->isGroupChatType((string) ($profile['chat_type'] ?? 'private'))
                ? $this->messages->findByChat((int) $bot['id'], (int) $profile['chat_id'])
                : $this->messages->findByDialog(
                    (int) $bot['id'],
                    (int) $profile['id'],
                    (int) $profile['chat_id'],
                );
            $latestUpdate = $this->updates->findLatestByBot((int) $bot['id']);
            if ($latestUpdate !== null) {
                $latestDeliveryAttempt = $this->deliveryAttempts->findLatestByUpdate((int) $latestUpdate['id']);
            }
            $pendingUpdateCount = $this->updates->countPendingByBot((int) $bot['id']);
            $botCommands = $this->botCommands->allForBot((int) $bot['id']);
        }

        return [
            'title' => 'Чат',
            'profile' => $profile,
            'bot' => $bot,
            'messages' => $messages,
            'latestUpdate' => $latestUpdate,
            'latestDeliveryAttempt' => $latestDeliveryAttempt,
            'pendingUpdateCount' => $pendingUpdateCount,
            'botCommands' => $botCommands,
            'selectedProfileId' => (int) ($_GET['profile_id'] ?? 0),
            'selectedBotId' => (int) ($_GET['bot_id'] ?? 0),
        ];
    }

    private function send(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $profile = $this->enabledUserById($profileId);
        $bot = $this->enabledBotById($botId);

        if ($profile === null || $bot === null) {
            Response::redirect('/chat');
            return;
        }

        $text = trim((string) ($_POST['text'] ?? ''));

        if ($text === '') {
            Response::redirect('/chat');
            return;
        }

        $chatId = (int) $profile['chat_id'];
        $botId = (int) $bot['id'];
        $profileId = (int) $profile['id'];

        // Сохраняем сообщение пользователя.
        $this->messages->create([
            'bot_id' => $botId,
            'profile_id' => $profileId,
            'chat_id' => $chatId,
            'direction' => 'user',
            'text' => $text,
        ]);

        // Получаем только что созданное сообщение для генерации Update.
        $allMessages = $this->messages->findByDialog($botId, $profileId, $chatId);
        $lastMessage = end($allMessages) ?: null;

        if ($lastMessage !== null) {
            $updatePayload = $this->updateGenerator->generate($lastMessage, $profile, $bot);
            $updatePayloadJson = json_encode($updatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $createdUpdate = $this->updates->create([
                'bot_id' => $botId,
                'profile_id' => $profileId,
                'payload' => $updatePayloadJson,
                'delivery_mode' => $bot['delivery_mode'] ?? 'long_polling',
                'queue_state' => 'pending',
            ]);

            $this->database->pdo()->prepare(
                'UPDATE messages SET raw_payload = :payload WHERE id = :id'
            )->execute([
                'payload' => $createdUpdate['payload'],
                'id' => $lastMessage['id'],
            ]);

            if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
                $this->webhookDelivery->deliver($createdUpdate, $bot, $this->webhookTimeoutSeconds());
            }
        }

        Response::redirect('/chat?profile_id=' . $profileId . '&bot_id=' . $botId);
    }

    private function callback(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $callbackData = (string) ($_POST['callback_data'] ?? '');
        $profile = $this->enabledUserById($profileId);
        $bot = $this->enabledBotById($botId);
        $message = $messageId > 0 ? $this->messages->find($messageId) : null;

        if (
            $profile === null
            || $bot === null
            || $message === null
            || $callbackData === ''
            || (int) $message['bot_id'] !== (int) $bot['id']
            || (int) $message['profile_id'] !== (int) $profile['id']
            || (string) $message['direction'] !== 'bot'
        ) {
            Response::redirect('/chat');
            return;
        }

        $updatePayload = $this->updateGenerator->generateCallbackQuery($message, $profile, $bot, $callbackData);
        $updatePayloadJson = json_encode($updatePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $createdUpdate = $this->updates->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'payload' => $updatePayloadJson,
            'delivery_mode' => $bot['delivery_mode'] ?? 'long_polling',
            'queue_state' => 'pending',
        ]);

        if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
            $this->webhookDelivery->deliver($createdUpdate, $bot, $this->webhookTimeoutSeconds());
        }

        Response::redirect('/chat?profile_id=' . (int) $profile['id'] . '&bot_id=' . (int) $bot['id']);
    }

    private function clear(): void {
        $profileId = (int) ($_POST['profile_id'] ?? 0);
        $botId = (int) ($_POST['bot_id'] ?? 0);
        $profile = $this->enabledUserById($profileId);
        $bot = $this->enabledBotById($botId);

        if ($profile === null || $bot === null || (string) ($_POST['confirm_clear'] ?? '') !== '1') {
            Response::json([
                'ok' => false,
                'error' => 'Для очистки диалога нужно выбрать пользователя, бота и подтвердить действие',
            ], 400);
            return;
        }

        $this->messages->deleteByDialog((int) $bot['id'], (int) $profile['id'], (int) $profile['chat_id']);
        $this->updates->deleteByDialog((int) $bot['id'], (int) $profile['id']);

        Response::redirect('/chat?profile_id=' . (int) $profile['id'] . '&bot_id=' . (int) $bot['id']);
    }

    /**
     * Возвращает выбранного пользователя из query string.
     *
     * @return array<string, mixed>|null
     */
    private function selectedUser(): ?array {
        return $this->enabledUserById((int) ($_GET['profile_id'] ?? 0));
    }

    /**
     * Возвращает выбранного бота из query string.
     *
     * @return array<string, mixed>|null
     */
    private function selectedBot(): ?array {
        return $this->enabledBotById((int) ($_GET['bot_id'] ?? 0));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enabledUserById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        $profile = $this->profiles->find($id);

        if ($profile === null || ((int) $profile['enabled']) !== 1) {
            return null;
        }

        return $profile;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function enabledBotById(int $id): ?array {
        if ($id <= 0) {
            return null;
        }

        $bot = $this->bots->find($id);

        if ($bot === null || ((int) $bot['enabled']) !== 1) {
            return null;
        }

        return $bot;
    }

    private function isGroupChatType(string $chatType): bool {
        return in_array($chatType, ['group', 'supergroup'], true);
    }

    private function webhookTimeoutSeconds(): int {
        return (int) ($this->webhookTimeoutSeconds)();
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
     * @param array<string, mixed> $data
     */
    private function renderPartial(string $template, array $data = []): void {
        $data['allUsers'] = $this->profiles->all();
        $data['allBots'] = $this->bots->all();
        $this->view->renderPartial($template, $data);
    }
}

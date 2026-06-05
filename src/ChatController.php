<?php

declare(strict_types=1);

namespace App;

use Closure;
use RuntimeException;

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
        private MediaStorage $mediaStorage,
        private BotApiPayloadFactory $payloadFactory,
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
            $botCommands = $this->botCommands->forChatContext(
                (int) $bot['id'],
                (int) $profile['chat_id'],
                (string) $profile['chat_type'],
                (int) $profile['user_id'],
                (string) $profile['language_code'],
            );
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
            'mediaStorage' => $this->mediaStorage,
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

        $messageData = $this->messageDataFromPost();
        if ($messageData === null) {
            Response::redirect('/chat?profile_id=' . $profileId . '&bot_id=' . $botId);
            return;
        }

        $chatId = (int) $profile['chat_id'];
        $botId = (int) $bot['id'];
        $profileId = (int) $profile['id'];

        $this->messages->create([
            'bot_id' => $botId,
            'profile_id' => $profileId,
            'chat_id' => $chatId,
            'direction' => 'user',
            'text' => $messageData['text'],
            'raw_payload' => $messageData['raw_payload'],
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

    /**
     * @return array{text: string, raw_payload: string|null}|null
     */
    private function messageDataFromPost(): ?array {
        $messageType = (string) ($_POST['message_type'] ?? 'text');

        if ($messageType === 'text') {
            $text = trim((string) ($_POST['text'] ?? ''));
            if ($text === '') {
                return null;
            }

            return [
                'text' => $text,
                'raw_payload' => null,
            ];
        }

        if ($messageType === 'photo') {
            $photo = trim((string) ($_POST['photo'] ?? ''));
            $storedMedia = $this->storeUploadedFile('photo_file');
            if ($storedMedia !== null) {
                $photo = $storedMedia['file_id'];
            }

            if ($photo === '') {
                return null;
            }

            $caption = trim((string) ($_POST['caption'] ?? ''));
            $rawPayload = [
                'photo' => $this->photoSizesPayload($photo, $storedMedia),
                'photo_source' => $photo,
            ];
            if ($caption !== '') {
                $rawPayload['caption'] = $caption;
            }

            return [
                'text' => $caption,
                'raw_payload' => $this->encodePayload($rawPayload),
            ];
        }

        if ($messageType === 'document') {
            $document = trim((string) ($_POST['document'] ?? ''));
            $storedMedia = $this->storeUploadedFile('document_file');
            if ($storedMedia !== null) {
                $document = $storedMedia['file_id'];
            }

            if ($document === '') {
                return null;
            }

            $caption = trim((string) ($_POST['caption'] ?? ''));
            $rawPayload = [
                'document' => $this->documentPayload($document, $storedMedia),
                'document_source' => $document,
            ];
            if ($caption !== '') {
                $rawPayload['caption'] = $caption;
            }

            return [
                'text' => $caption,
                'raw_payload' => $this->encodePayload($rawPayload),
            ];
        }

        if (in_array($messageType, ['video', 'animation', 'audio', 'voice', 'video_note', 'sticker'], true)) {
            return $this->typedMediaMessageData($messageType);
        }

        if ($messageType === 'poll') {
            $question = trim((string) ($_POST['question'] ?? ''));
            $options = $this->pollOptionsFromPost();
            if ($question === '' || count($options) < 2) {
                return null;
            }

            return [
                'text' => '[poll] ' . $question,
                'raw_payload' => $this->encodePayload([
                    'poll' => [
                        'id' => substr(sha1($question . json_encode($options, JSON_THROW_ON_ERROR)), 0, 16),
                        'question' => $question,
                        'options' => array_map(
                            fn(string $option): array => ['text' => $option, 'voter_count' => 0],
                            $options,
                        ),
                        'total_voter_count' => 0,
                        'is_closed' => false,
                        'is_anonymous' => true,
                        'type' => (string) ($_POST['poll_type'] ?? 'regular') === 'quiz' ? 'quiz' : 'regular',
                        'allows_multiple_answers' => false,
                    ],
                ]),
            ];
        }

        if ($messageType === 'location') {
            $latitude = $this->floatPostParam('latitude');
            $longitude = $this->floatPostParam('longitude');
            if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                return null;
            }

            return [
                'text' => '[location]',
                'raw_payload' => $this->encodePayload([
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ],
                ]),
            ];
        }

        if ($messageType === 'venue') {
            $latitude = $this->floatPostParam('latitude');
            $longitude = $this->floatPostParam('longitude');
            $title = trim((string) ($_POST['title'] ?? ''));
            $address = trim((string) ($_POST['address'] ?? ''));
            if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || $title === '' || $address === '') {
                return null;
            }

            return [
                'text' => '[venue] ' . $title,
                'raw_payload' => $this->encodePayload([
                    'venue' => [
                        'location' => [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                        ],
                        'title' => $title,
                        'address' => $address,
                    ],
                ]),
            ];
        }

        if ($messageType === 'contact') {
            $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
            $firstName = trim((string) ($_POST['first_name'] ?? ''));
            if ($phoneNumber === '' || $firstName === '') {
                return null;
            }

            $contact = [
                'phone_number' => $phoneNumber,
                'first_name' => $firstName,
            ];
            $lastName = trim((string) ($_POST['last_name'] ?? ''));
            if ($lastName !== '') {
                $contact['last_name'] = $lastName;
            }

            return [
                'text' => '[contact] ' . $firstName,
                'raw_payload' => $this->encodePayload(['contact' => $contact]),
            ];
        }

        if ($messageType === 'dice') {
            $emoji = trim((string) ($_POST['emoji'] ?? ''));
            $value = $this->intPostParam('value', 1);
            if ($emoji === '') {
                $emoji = 'dice';
            }

            return [
                'text' => '[dice] value ' . $value,
                'raw_payload' => $this->encodePayload([
                    'dice' => [
                        'emoji' => $emoji,
                        'value' => max(1, min(6, $value)),
                    ],
                ]),
            ];
        }

        return null;
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

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{text: string, raw_payload: string|null}|null
     */
    private function typedMediaMessageData(string $mediaField): ?array {
        $media = trim((string) ($_POST[$mediaField] ?? ''));
        $storedMedia = $this->storeUploadedFile($mediaField . '_file');
        if ($storedMedia !== null) {
            $media = $storedMedia['file_id'];
        }

        if ($media === '') {
            return null;
        }

        $caption = trim((string) ($_POST['caption'] ?? ''));
        $rawPayload = [
            $mediaField => $this->payloadFactory->typedMedia($mediaField, $media, $_POST, $storedMedia),
            $mediaField . '_source' => $media,
        ];
        if ($caption !== '' && in_array($mediaField, ['video', 'animation', 'audio'], true)) {
            $rawPayload['caption'] = $caption;
        }

        return [
            'text' => $caption !== '' ? $caption : '[' . $mediaField . ']',
            'raw_payload' => $this->encodePayload($rawPayload),
        ];
    }

    /**
     * @return list<string>
     */
    private function pollOptionsFromPost(): array {
        $rawOptions = str_replace(',', "\n", (string) ($_POST['options'] ?? ''));
        $options = [];
        foreach (preg_split('/\R/u', $rawOptions) ?: [] as $option) {
            $option = trim($option);
            if ($option !== '') {
                $options[] = $option;
            }
        }

        return array_slice($options, 0, 10);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function photoSizesPayload(string $photo, ?array $storedMedia = null): array {
        $payload = [
            [
                'file_id' => $photo,
                'file_unique_id' => $storedMedia['file_unique_id'] ?? substr(sha1($photo), 0, 16),
                'width' => 0,
                'height' => 0,
            ],
        ];

        if ($storedMedia !== null) {
            $payload[0]['file_size'] = $storedMedia['file_size'];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(string $document, ?array $storedMedia = null): array {
        if ($storedMedia !== null) {
            return [
                'file_id' => $document,
                'file_unique_id' => $storedMedia['file_unique_id'],
                'file_name' => $storedMedia['file_name'],
                'mime_type' => $storedMedia['mime_type'],
                'file_size' => $storedMedia['file_size'],
            ];
        }

        $path = parse_url($document, PHP_URL_PATH);
        $fileName = basename((string) ($path ?: $document));

        return [
            'file_id' => $document,
            'file_unique_id' => substr(sha1($document), 0, 16),
            'file_name' => $fileName === '' ? null : $fileName,
        ];
    }

    /**
     * @return array{file_id: string, file_unique_id: string, file_name: string, mime_type: string, file_size: int, file_path: string}|null
     */
    private function storeUploadedFile(string $field): ?array {
        $uploadedFile = $this->uploadedFile($field);
        if ($uploadedFile === null) {
            return null;
        }

        try {
            return $this->mediaStorage->storeUploadedFile($uploadedFile);
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * @return array{name: string, filename: string, content_type: string, content: string, size: int}|null
     */
    private function uploadedFile(string $field): ?array {
        $files = $_POST[BotApiRequestParser::FILES_KEY] ?? null;
        if (!is_array($files) || !isset($files[$field]) || !is_array($files[$field])) {
            return null;
        }

        $file = $files[$field];
        if (!isset($file['filename'], $file['content'])) {
            return null;
        }

        return [
            'name' => (string) ($file['name'] ?? $field),
            'filename' => (string) $file['filename'],
            'content_type' => (string) ($file['content_type'] ?? 'application/octet-stream'),
            'content' => (string) $file['content'],
            'size' => (int) ($file['size'] ?? strlen((string) $file['content'])),
        ];
    }

    private function floatPostParam(string $name): ?float {
        $value = $_POST[$name] ?? null;
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function intPostParam(string $name, int $default): int {
        $value = $_POST[$name] ?? null;
        if (is_int($value)) {
            return $value;
        }

        $value = trim((string) $value);
        if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
            return $default;
        }

        return (int) $value;
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

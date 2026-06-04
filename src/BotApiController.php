<?php

declare(strict_types=1);

namespace App;

/**
 * Обрабатывает локальные Telegram Bot API маршруты `/bot<TOKEN>/<METHOD>`.
 */
final readonly class BotApiController {

    public function __construct(
        private BotRepository $bots,
        private BotCommandRepository $botCommands,
        private ProfileRepository $profiles,
        private MessageRepository $messages,
        private UpdateRepository $updates,
        private DeliveryAttemptRepository $deliveryAttempts,
    ) {
    }

    public function handle(string $method, string $path): bool {
        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getMe$#i', $path, $matches) === 1) {
            $this->getMe($matches[1]);
            return true;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getUpdates$#i', $path, $matches) === 1) {
            $this->getUpdates($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendMessage$#i', $path, $matches) === 1) {
            $this->sendMessage($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendPhoto$#i', $path, $matches) === 1) {
            $this->sendPhoto($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendDocument$#i', $path, $matches) === 1) {
            $this->sendDocument($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendVideo$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'video');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendAnimation$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'animation');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendAudio$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'audio');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendVoice$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'voice');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendVideoNote$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'video_note');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendSticker$#i', $path, $matches) === 1) {
            $this->sendTypedMedia($matches[1], 'sticker');
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendLocation$#i', $path, $matches) === 1) {
            $this->sendLocation($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendVenue$#i', $path, $matches) === 1) {
            $this->sendVenue($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendContact$#i', $path, $matches) === 1) {
            $this->sendContact($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendDice$#i', $path, $matches) === 1) {
            $this->sendDice($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/editMessageText$#i', $path, $matches) === 1) {
            $this->editMessageText($matches[1]);
            return true;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getWebhookInfo$#i', $path, $matches) === 1) {
            $this->getWebhookInfo($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/setMyCommands$#i', $path, $matches) === 1) {
            $this->setMyCommands($matches[1]);
            return true;
        }

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getMyCommands$#i', $path, $matches) === 1) {
            $this->getMyCommands($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/deleteMyCommands$#i', $path, $matches) === 1) {
            $this->deleteMyCommands($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/answerCallbackQuery$#i', $path, $matches) === 1) {
            $this->answerCallbackQuery($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/setWebhook$#i', $path, $matches) === 1) {
            $this->setWebhook($matches[1]);
            return true;
        }

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/deleteWebhook$#i', $path, $matches) === 1) {
            $this->deleteWebhook($matches[1]);
            return true;
        }

        if (preg_match('#^/bot([^/]+)/#', $path) === 1) {
            Response::json([
                'ok' => false,
                'error_code' => 501,
                'description' => 'Метод пока не поддерживается эмулятором',
            ], 501);
            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function botApiParams(): array {
        return array_replace($_GET, $_POST);
    }

    private function getMe(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        Response::json([
            'ok' => true,
            'result' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'],
                'username' => $bot['username'],
                'can_join_groups' => true,
                'can_read_all_group_messages' => false,
                'supports_inline_queries' => false,
            ],
        ]);
    }

    private function getWebhookInfo(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $result = [
            'url' => (string) ($bot['webhook_url'] ?? ''),
            'has_custom_certificate' => false,
            'pending_update_count' => $this->updates->countPendingByBot((int) $bot['id']),
            'max_connections' => 40,
        ];
        $latestFailedAttempt = $this->deliveryAttempts->findLatestFailedByBot((int) $bot['id']);
        if ($latestFailedAttempt !== null) {
            $result['last_error_date'] = strtotime((string) $latestFailedAttempt['created_at']) ?: time();
            $result['last_error_message'] = $latestFailedAttempt['error'];
        }

        Response::json([
            'ok' => true,
            'result' => $result,
        ]);
    }

    private function sendMessage(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, 'text')) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $replyMarkup = ReplyMarkup::fromBotApiParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            $this->badRequest('Bad Request: object expected as reply markup');
            return;
        }

        $message = $this->messages->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'chat_id' => $chatId,
            'direction' => 'bot',
            'text' => trim((string) $params['text']),
            'raw_payload' => ReplyMarkup::encodeOnly($replyMarkup),
        ]);

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($message, $profile, $bot),
        ]);
    }

    private function editMessageText(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, 'message_id') || !$this->requireParam($params, 'text')) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $messageId = $this->intParam($params['message_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        if ($messageId <= 0) {
            $this->badRequest('Bad Request: message to edit not found');
            return;
        }

        $message = $this->messages->findBotMessageByTelegramId((int) $bot['id'], $chatId, $messageId);
        if ($message === null) {
            $this->badRequest('Bad Request: message to edit not found');
            return;
        }

        $replyMarkup = ReplyMarkup::fromBotApiParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            $this->badRequest('Bad Request: object expected as reply markup');
            return;
        }

        $updatedMessage = $this->messages->updateBotMessage(
            (int) $message['id'],
            trim((string) $params['text']),
            ReplyMarkup::encodeOnly($replyMarkup),
        );

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($updatedMessage, $profile, $bot),
        ]);
    }

    private function sendPhoto(string $token): void {
        $this->sendMedia($token, 'photo');
    }

    private function sendDocument(string $token): void {
        $this->sendMedia($token, 'document');
    }

    private function sendTypedMedia(string $token, string $mediaField): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, $mediaField)) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $media = trim((string) $params[$mediaField]);
        $caption = trim((string) ($params['caption'] ?? ''));
        $rawPayload = [
            $mediaField => $this->typedMediaPayload($mediaField, $media, $params),
            $mediaField . '_source' => $media,
        ];
        if ($caption !== '' && in_array($mediaField, ['video', 'animation', 'audio'], true)) {
            $rawPayload['caption'] = $caption;
        }

        $text = $caption !== '' ? $caption : '[' . $mediaField . ']';
        $this->sendStructuredMessage($bot, $profile, $chatId, $rawPayload, $text);
    }

    private function sendLocation(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, 'latitude') || !$this->requireParam($params, 'longitude')) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $location = $this->locationPayload($params);
        if ($location === null) {
            return;
        }

        $this->sendStructuredMessage($bot, $profile, $chatId, ['location' => $location], '[location]');
    }

    private function sendVenue(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (
            !$this->requireParam($params, 'chat_id')
            || !$this->requireParam($params, 'latitude')
            || !$this->requireParam($params, 'longitude')
            || !$this->requireParam($params, 'title')
            || !$this->requireParam($params, 'address')
        ) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $location = $this->locationPayload($params);
        if ($location === null) {
            return;
        }

        $venue = [
            'location' => $location,
            'title' => trim((string) $params['title']),
            'address' => trim((string) $params['address']),
        ];
        foreach (['foursquare_id', 'foursquare_type', 'google_place_id', 'google_place_type'] as $optionalField) {
            if (isset($params[$optionalField]) && trim((string) $params[$optionalField]) !== '') {
                $venue[$optionalField] = trim((string) $params[$optionalField]);
            }
        }

        $this->sendStructuredMessage($bot, $profile, $chatId, ['venue' => $venue], '[venue] ' . $venue['title']);
    }

    private function sendContact(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, 'phone_number') || !$this->requireParam($params, 'first_name')) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $contact = [
            'phone_number' => trim((string) $params['phone_number']),
            'first_name' => trim((string) $params['first_name']),
        ];
        foreach (['last_name', 'vcard'] as $optionalField) {
            if (isset($params[$optionalField]) && trim((string) $params[$optionalField]) !== '') {
                $contact[$optionalField] = trim((string) $params[$optionalField]);
            }
        }

        $this->sendStructuredMessage($bot, $profile, $chatId, ['contact' => $contact], '[contact] ' . $contact['first_name']);
    }

    private function sendDice(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id')) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $emoji = trim((string) ($params['emoji'] ?? "\u{1F3B2}"));
        if (!$this->isSupportedDiceEmoji($emoji)) {
            $this->badRequest('Bad Request: unsupported dice emoji');
            return;
        }

        $dice = [
            'emoji' => $emoji,
            'value' => $emoji === "\u{1F3B0}" ? 32 : 4,
        ];

        $this->sendStructuredMessage($bot, $profile, $chatId, ['dice' => $dice], '[dice] ' . $emoji);
    }

    /**
     * @param array<string, mixed> $bot
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $rawPayload
     */
    private function sendStructuredMessage(array $bot, array $profile, int $chatId, array $rawPayload, string $text): void {
        $params = $this->botApiParams();
        $replyMarkup = ReplyMarkup::fromBotApiParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            $this->badRequest('Bad Request: object expected as reply markup');
            return;
        }

        $rawPayload = ReplyMarkup::withReplyMarkup($rawPayload, $replyMarkup);

        $message = $this->messages->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'chat_id' => $chatId,
            'direction' => 'bot',
            'text' => $text,
            'raw_payload' => ReplyMarkup::encodePayload($rawPayload),
        ]);

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($message, $profile, $bot),
        ]);
    }

    private function sendMedia(string $token, string $mediaField): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, $mediaField)) {
            return;
        }

        $chatId = $this->intParam($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $replyMarkup = ReplyMarkup::fromBotApiParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            $this->badRequest('Bad Request: object expected as reply markup');
            return;
        }

        $media = trim((string) $params[$mediaField]);
        $caption = trim((string) ($params['caption'] ?? ''));
        $rawPayload = $mediaField === 'photo'
            ? [
                'photo' => $this->photoSizesPayload($media),
                'photo_source' => $media,
            ]
            : [
                'document' => $this->documentPayload($media),
                'document_source' => $media,
            ];
        if ($caption !== '') {
            $rawPayload['caption'] = $caption;
        }
        $rawPayload = ReplyMarkup::withReplyMarkup($rawPayload, $replyMarkup);

        $message = $this->messages->create([
            'bot_id' => (int) $bot['id'],
            'profile_id' => (int) $profile['id'],
            'chat_id' => $chatId,
            'direction' => 'bot',
            'text' => $caption,
            'raw_payload' => ReplyMarkup::encodePayload($rawPayload),
        ]);

        Response::json([
            'ok' => true,
            'result' => $this->botMessagePayload($message, $profile, $bot),
        ]);
    }

    private function setMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        $commands = $this->commandsParam($params['commands'] ?? null);

        if ($commands === null) {
            $this->badRequest('Bad Request: parameter "commands" is required');
            return;
        }

        $this->botCommands->replaceForBot((int) $bot['id'], $commands);

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    private function getMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        Response::json([
            'ok' => true,
            'result' => $this->botCommands->allForBot((int) $bot['id']),
        ]);
    }

    private function deleteMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $this->botCommands->deleteForBot((int) $bot['id']);

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    private function answerCallbackQuery(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'callback_query_id')) {
            return;
        }

        Response::json([
            'ok' => true,
            'result' => true,
        ]);
    }

    private function getUpdates(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        if (($bot['delivery_mode'] ?? 'long_polling') === 'webhook' && trim((string) ($bot['webhook_url'] ?? '')) !== '') {
            Response::json([
                'ok' => false,
                'error_code' => 409,
                'description' => 'Conflict: can\'t use getUpdates method while webhook is active; use deleteWebhook to delete the webhook first',
            ], 409);
            return;
        }

        $params = $this->botApiParams();
        $offset = $this->intParam($params['offset'] ?? 0, 0);
        $limit = max(1, min(100, $this->intParam($params['limit'] ?? 100, 100)));
        $timeout = max(0, $this->intParam($params['timeout'] ?? 0, 0));
        $allowedUpdates = $this->allowedUpdatesParam($params['allowed_updates'] ?? null);
        $botId = (int) $bot['id'];
        $waitUntil = microtime(true) + min($timeout, 3);

        do {
            $updates = $this->pendingUpdates($botId, $offset, $limit);
            $result = $this->updatesResult($updates, $allowedUpdates);

            if ($result !== [] || $timeout === 0 || microtime(true) >= $waitUntil) {
                Response::json([
                    'ok' => true,
                    'result' => $result,
                ]);
                return;
            }

            usleep(250_000);
        } while (true);
    }

    private function setWebhook(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();

        if (!array_key_exists('url', $params)) {
            $this->badRequest('Bad Request: parameter "url" is required');
            return;
        }

        $url = trim((string) $params['url']);
        $secretToken = trim((string) ($params['secret_token'] ?? $params['webhook_secret_token'] ?? ''));

        if ($url === '') {
            $this->bots->setWebhook((int) $bot['id'], null, null);
            Response::json([
                'ok' => true,
                'result' => true,
                'description' => 'Webhook was deleted',
            ]);
            return;
        }

        if (!$this->isValidWebhookUrl($url)) {
            $this->badRequest('Bad Request: invalid webhook URL');
            return;
        }

        $this->bots->setWebhook(
            (int) $bot['id'],
            $url,
            $secretToken === '' ? null : $secretToken,
        );

        Response::json([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was set',
        ]);
    }

    private function deleteWebhook(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();

        $this->bots->setWebhook((int) $bot['id'], null, null);

        if ($this->isTruthyBotApiParam($params['drop_pending_updates'] ?? false)) {
            $this->updates->dropPendingByBot((int) $bot['id']);
        }

        Response::json([
            'ok' => true,
            'result' => true,
            'description' => 'Webhook was deleted',
        ]);
    }

    private function botNotFound(): void {
        Response::json([
            'ok' => false,
            'error_code' => 404,
            'description' => 'Бот не найден',
        ], 404);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function requireParam(array $params, string $name): bool {
        if (array_key_exists($name, $params) && trim((string) $params[$name]) !== '') {
            return true;
        }

        $this->badRequest('Bad Request: parameter "' . $name . '" is required');
        return false;
    }

    private function badRequest(string $description): void {
        Response::json([
            'ok' => false,
            'error_code' => 400,
            'description' => $description,
        ], 400);
    }

    private function profileByChatId(int $chatId): ?array {
        if ($chatId === 0) {
            $this->badRequest('Bad Request: chat not found');
            return null;
        }

        $profile = $this->profiles->findEnabledByChat($chatId);
        if ($profile === null) {
            $this->badRequest('Bad Request: chat not found');
        }

        return $profile;
    }

    private function isValidWebhookUrl(string $url): bool {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        return $host !== '' && in_array($scheme, ['http', 'https'], true);
    }

    private function isTruthyBotApiParam(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
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

    /**
     * @return list<string>|null
     */
    private function allowedUpdatesParam(mixed $value): ?array {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value) || $value === []) {
            return null;
        }

        $allowedUpdates = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $allowedUpdates[] = $item;
            }
        }

        return $allowedUpdates === [] ? null : $allowedUpdates;
    }

    /**
     * @return list<array{command: string, description: string}>|null
     */
    private function commandsParam(mixed $value): ?array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($value)) {
            return null;
        }

        $commands = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                return null;
            }

            $command = ltrim(trim((string) ($item['command'] ?? '')), '/');
            $description = trim((string) ($item['description'] ?? ''));

            if (preg_match('/^[a-z0-9_]{1,32}$/', $command) !== 1 || $description === '' || mb_strlen($description) > 256) {
                return null;
            }

            $commands[] = [
                'command' => $command,
                'description' => $description,
            ];
        }

        return count($commands) > 100 ? null : $commands;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingUpdates(int $botId, int $offset, int $limit): array {
        if ($offset < 0) {
            $updates = $this->updates->findLastPending($botId, abs($offset));
            if ($updates !== []) {
                $this->updates->confirmBeforeOffset($botId, (int) $updates[0]['update_id']);
            }

            return array_slice($updates, 0, $limit);
        }

        $this->updates->confirmBeforeOffset($botId, $offset);

        return $this->updates->findPending($botId, $limit, $offset);
    }

    /**
     * @param list<array<string, mixed>> $updates
     * @param list<string>|null $allowedUpdates
     * @return list<array<string, mixed>>
     */
    private function updatesResult(array $updates, ?array $allowedUpdates): array {
        $result = [];

        foreach ($updates as $update) {
            $payload = json_decode((string) $update['payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            $payload['update_id'] = (int) $update['update_id'];
            if ($allowedUpdates !== null && !$this->isAllowedUpdate($payload, $allowedUpdates)) {
                continue;
            }

            $result[] = $payload;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $allowedUpdates
     */
    private function isAllowedUpdate(array $payload, array $allowedUpdates): bool {
        foreach (array_keys($payload) as $key) {
            if ($key !== 'update_id' && in_array($key, $allowedUpdates, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $bot
     * @return array<string, mixed>
     */
    private function botMessagePayload(array $message, array $profile, array $bot): array {
        $rawPayload = json_decode((string) ($message['raw_payload'] ?? ''), true);
        $payload = [
            'message_id' => (int) $message['telegram_message_id'],
            'from' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'],
                'username' => $bot['username'],
            ],
            'chat' => $this->chatPayload($profile),
            'date' => strtotime((string) $message['created_at']) ?: time(),
        ];

        if (is_array($rawPayload) && isset($rawPayload['photo']) && is_array($rawPayload['photo'])) {
            $payload['photo'] = $rawPayload['photo'];
            if (isset($rawPayload['caption']) && (string) $rawPayload['caption'] !== '') {
                $payload['caption'] = (string) $rawPayload['caption'];
            }
        } elseif (is_array($rawPayload) && isset($rawPayload['document']) && is_array($rawPayload['document'])) {
            $payload['document'] = $rawPayload['document'];
            if (isset($rawPayload['caption']) && (string) $rawPayload['caption'] !== '') {
                $payload['caption'] = (string) $rawPayload['caption'];
            }
        } elseif (is_array($rawPayload) && isset($rawPayload['location']) && is_array($rawPayload['location'])) {
            $payload['location'] = $rawPayload['location'];
        } elseif (is_array($rawPayload) && isset($rawPayload['venue']) && is_array($rawPayload['venue'])) {
            $payload['venue'] = $rawPayload['venue'];
        } elseif (is_array($rawPayload) && isset($rawPayload['contact']) && is_array($rawPayload['contact'])) {
            $payload['contact'] = $rawPayload['contact'];
        } elseif (is_array($rawPayload) && isset($rawPayload['dice']) && is_array($rawPayload['dice'])) {
            $payload['dice'] = $rawPayload['dice'];
        } elseif (is_array($rawPayload)) {
            foreach (['video', 'animation', 'audio', 'voice', 'video_note', 'sticker'] as $mediaField) {
                if (isset($rawPayload[$mediaField]) && is_array($rawPayload[$mediaField])) {
                    $payload[$mediaField] = $rawPayload[$mediaField];
                    if (isset($rawPayload['caption']) && (string) $rawPayload['caption'] !== '' && in_array($mediaField, ['video', 'animation', 'audio'], true)) {
                        $payload['caption'] = (string) $rawPayload['caption'];
                    }
                    break;
                }
            }
            if (!array_intersect(['video', 'animation', 'audio', 'voice', 'video_note', 'sticker'], array_keys($payload))) {
                $payload['text'] = $message['text'];
            }
        } else {
            $payload['text'] = $message['text'];
        }

        $replyMarkup = ReplyMarkup::fromMessage($message);
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $profile
     * @return array<string, mixed>
     */
    private function chatPayload(array $profile): array {
        $chatType = (string) ($profile['chat_type'] ?? 'private');
        $payload = [
            'id' => (int) ($profile['chat_id'] ?? 0),
            'type' => $chatType,
        ];

        if (in_array($chatType, ['group', 'supergroup', 'channel'], true)) {
            $payload['title'] = 'Chat ' . (string) ($profile['chat_id'] ?? '0');

            return $payload;
        }

        $payload['username'] = $profile['username'] ?? '';
        $payload['first_name'] = $profile['first_name'] ?? '';
        $payload['last_name'] = $profile['last_name'] ?? '';

        return $payload;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function photoSizesPayload(string $photo): array {
        return [
            [
                'file_id' => $photo,
                'file_unique_id' => substr(sha1($photo), 0, 16),
                'width' => 0,
                'height' => 0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function documentPayload(string $document): array {
        $path = parse_url($document, PHP_URL_PATH);
        $fileName = basename((string) ($path ?: $document));

        return [
            'file_id' => $document,
            'file_unique_id' => substr(sha1($document), 0, 16),
            'file_name' => $fileName === '' ? null : $fileName,
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function typedMediaPayload(string $mediaField, string $fileId, array $params): array {
        $payload = [
            'file_id' => $fileId,
            'file_unique_id' => substr(sha1($mediaField . ':' . $fileId), 0, 16),
        ];

        foreach ($this->typedMediaOptionalFields($mediaField) as $field => $type) {
            if (!isset($params[$field]) || trim((string) $params[$field]) === '') {
                continue;
            }

            $payload[$field] = $type === 'int'
                ? $this->intParam($params[$field], 0)
                : trim((string) $params[$field]);
        }

        if (($mediaField === 'audio' || $mediaField === 'document') && !isset($payload['file_name'])) {
            $path = parse_url($fileId, PHP_URL_PATH);
            $fileName = basename((string) ($path ?: $fileId));
            if ($fileName !== '') {
                $payload['file_name'] = $fileName;
            }
        }

        return $payload;
    }

    /**
     * @return array<string, 'int'|'string'>
     */
    private function typedMediaOptionalFields(string $mediaField): array {
        return match ($mediaField) {
            'video' => [
                'width' => 'int',
                'height' => 'int',
                'duration' => 'int',
                'file_name' => 'string',
                'mime_type' => 'string',
            ],
            'animation' => [
                'width' => 'int',
                'height' => 'int',
                'duration' => 'int',
                'file_name' => 'string',
                'mime_type' => 'string',
            ],
            'audio' => [
                'duration' => 'int',
                'performer' => 'string',
                'title' => 'string',
                'file_name' => 'string',
                'mime_type' => 'string',
            ],
            'voice' => [
                'duration' => 'int',
                'mime_type' => 'string',
            ],
            'video_note' => [
                'duration' => 'int',
                'length' => 'int',
            ],
            'sticker' => [
                'type' => 'string',
                'width' => 'int',
                'height' => 'int',
                'emoji' => 'string',
                'set_name' => 'string',
            ],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return array{latitude: float, longitude: float}|null
     */
    private function locationPayload(array $params): ?array {
        $latitude = $this->floatParam($params['latitude']);
        $longitude = $this->floatParam($params['longitude']);
        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $this->badRequest('Bad Request: invalid location coordinates');
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function floatParam(mixed $value): ?float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function isSupportedDiceEmoji(string $emoji): bool {
        return in_array($emoji, [
            "\u{1F3B2}", // dice
            "\u{1F3AF}", // darts
            "\u{1F3C0}", // basketball
            "\u{26BD}", // football
            "\u{1F3B3}", // bowling
            "\u{1F3B0}", // slot machine
        ], true);
    }
}

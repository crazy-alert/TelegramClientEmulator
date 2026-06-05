<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

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
        private MediaStorage $mediaStorage,
        private BotApiPayloadFactory $payloadFactory,
        private LongPollingService $longPolling,
        private int $longPollingMaxTimeoutSeconds,
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

        if (($method === 'GET' || $method === 'POST') && preg_match('#^/bot([^/]+)/getFile$#i', $path, $matches) === 1) {
            $this->getFile($matches[1]);
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

        if ($method === 'POST' && preg_match('#^/bot([^/]+)/sendPoll$#i', $path, $matches) === 1) {
            $this->sendPoll($matches[1]);
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
        return BotApiParams::all($_GET, $_POST);
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

    private function getFile(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'file_id')) {
            return;
        }

        $file = $this->mediaStorage->findByFileId(trim((string) $params['file_id']));
        if ($file === null) {
            $this->badRequest('Bad Request: file not found');
            return;
        }

        Response::json([
            'ok' => true,
            'result' => $file,
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
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
            'result' => $this->payloadFactory->message($message, $profile, $bot),
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
        $messageId = BotApiParams::int($params['message_id'], 0);
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
            'result' => $this->payloadFactory->message($updatedMessage, $profile, $bot),
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
        $uploadedFile = $this->uploadedFile($params, $mediaField);
        if (!$this->requireParam($params, 'chat_id')) {
            return;
        }

        if ($uploadedFile === null && !$this->requireParam($params, $mediaField)) {
            return;
        }

        $chatId = BotApiParams::int($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $media = trim((string) ($params[$mediaField] ?? ''));
        $storedMedia = null;
        if ($uploadedFile !== null) {
            try {
                $storedMedia = $this->mediaStorage->storeUploadedFile($uploadedFile);
                $media = $storedMedia['file_id'];
            } catch (RuntimeException $exception) {
                $this->badRequest($exception->getMessage());
                return;
            }
        }

        $caption = trim((string) ($params['caption'] ?? ''));
        $rawPayload = [
            $mediaField => $this->payloadFactory->typedMedia($mediaField, $media, $params, $storedMedia),
            $mediaField . '_source' => $media,
        ];
        if ($caption !== '' && in_array($mediaField, ['video', 'animation', 'audio'], true)) {
            $rawPayload['caption'] = $caption;
        }

        $text = $caption !== '' ? $caption : '[' . $mediaField . ']';
        $this->sendStructuredMessage($bot, $profile, $chatId, $rawPayload, $text);
    }

    private function sendPoll(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        if (!$this->requireParam($params, 'chat_id') || !$this->requireParam($params, 'question') || !$this->requireParam($params, 'options')) {
            return;
        }

        $chatId = BotApiParams::int($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $question = trim((string) $params['question']);
        $options = BotApiParams::pollOptions($params['options']);
        if ($options === null || $question === '' || mb_strlen($question) > 300) {
            $this->badRequest('Bad Request: invalid poll parameters');
            return;
        }

        $pollType = trim((string) ($params['type'] ?? 'regular'));
        if (!in_array($pollType, ['regular', 'quiz'], true)) {
            $this->badRequest('Bad Request: invalid poll type');
            return;
        }

        $poll = [
            'id' => substr(hash('sha256', $question . ':' . json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), 0, 32),
            'question' => $question,
            'options' => $options,
            'total_voter_count' => 0,
            'is_closed' => BotApiParams::truthy($params['is_closed'] ?? false),
            'is_anonymous' => !array_key_exists('is_anonymous', $params) || BotApiParams::truthy($params['is_anonymous']),
            'type' => $pollType,
            'allows_multiple_answers' => BotApiParams::truthy($params['allows_multiple_answers'] ?? false),
        ];

        if ($pollType === 'quiz' && isset($params['correct_option_id']) && trim((string) $params['correct_option_id']) !== '') {
            $correctOptionId = BotApiParams::int($params['correct_option_id'], -1);
            if ($correctOptionId < 0 || $correctOptionId >= count($options)) {
                $this->badRequest('Bad Request: invalid correct_option_id');
                return;
            }
            $poll['correct_option_id'] = $correctOptionId;
        }

        if (isset($params['explanation']) && trim((string) $params['explanation']) !== '') {
            $poll['explanation'] = trim((string) $params['explanation']);
        }

        $this->sendStructuredMessage($bot, $profile, $chatId, ['poll' => $poll], '[poll] ' . $question);
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
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

        $chatId = BotApiParams::int($params['chat_id'], 0);
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
            'result' => $this->payloadFactory->message($message, $profile, $bot),
        ]);
    }

    private function sendMedia(string $token, string $mediaField): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        $uploadedFile = $this->uploadedFile($params, $mediaField);
        if (!$this->requireParam($params, 'chat_id')) {
            return;
        }

        if ($uploadedFile === null && !$this->requireParam($params, $mediaField)) {
            return;
        }

        $chatId = BotApiParams::int($params['chat_id'], 0);
        $profile = $this->profileByChatId($chatId);
        if ($profile === null) {
            return;
        }

        $replyMarkup = ReplyMarkup::fromBotApiParam($params['reply_markup'] ?? null);
        if (array_key_exists('reply_markup', $params) && $replyMarkup === null) {
            $this->badRequest('Bad Request: object expected as reply markup');
            return;
        }

        $media = trim((string) ($params[$mediaField] ?? ''));
        $storedMedia = null;
        if ($uploadedFile !== null) {
            try {
                $storedMedia = $this->mediaStorage->storeUploadedFile($uploadedFile);
                $media = $storedMedia['file_id'];
            } catch (RuntimeException $exception) {
                $this->badRequest($exception->getMessage());
                return;
            }
        }

        $caption = trim((string) ($params['caption'] ?? ''));
        $rawPayload = $mediaField === 'photo'
            ? [
                'photo' => $this->payloadFactory->photoSizes($media, $storedMedia),
                'photo_source' => $media,
            ]
            : [
                'document' => $this->payloadFactory->document($media, $storedMedia),
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
            'result' => $this->payloadFactory->message($message, $profile, $bot),
        ]);
    }

    private function setMyCommands(string $token): void {
        $bot = $this->bots->findByToken($token);

        if ($bot === null) {
            $this->botNotFound();
            return;
        }

        $params = $this->botApiParams();
        $commands = BotApiParams::commands($params['commands'] ?? null);

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
        $offset = BotApiParams::int($params['offset'] ?? 0, 0);
        $limit = BotApiParams::int($params['limit'] ?? 100, 100);
        $timeout = max(0, BotApiParams::int($params['timeout'] ?? 0, 0));
        $allowedUpdates = BotApiParams::allowedUpdates($params['allowed_updates'] ?? null);
        $botId = (int) $bot['id'];
        $waitUntil = microtime(true) + min($timeout, $this->longPollingMaxTimeoutSeconds);

        do {
            $result = $this->longPolling->result($botId, $offset, $limit, $allowedUpdates);

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

        if (BotApiParams::truthy($params['drop_pending_updates'] ?? false)) {
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

    /**
     * @param array<string, mixed> $params
     * @return array{name: string, filename: string, content_type: string, content: string, size: int}|null
     */
    private function uploadedFile(array $params, string $field): ?array {
        $files = $params[BotApiRequestParser::FILES_KEY] ?? null;
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

    /**
     * @param array<string, mixed> $params
     * @return array{latitude: float, longitude: float}|null
     */
    private function locationPayload(array $params): ?array {
        $latitude = BotApiParams::float($params['latitude']);
        $longitude = BotApiParams::float($params['longitude']);
        if ($latitude === null || $longitude === null || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            $this->badRequest('Bad Request: invalid location coordinates');
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
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

<?php

declare(strict_types=1);

namespace App;

/**
 * Собирает Telegram-like payload для ответов локального Bot API.
 */
final class BotApiPayloadFactory {

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $bot
     * @return array<string, mixed>
     */
    public function message(array $message, array $profile, array $bot): array {
        $rawPayload = json_decode((string) ($message['raw_payload'] ?? ''), true);
        $payload = [
            'message_id' => (int) $message['telegram_message_id'],
            'from' => [
                'id' => (int) ($bot['bot_id'] ?? 0),
                'is_bot' => true,
                'first_name' => $bot['display_name'],
                'username' => $bot['username'],
            ],
            'chat' => $this->chat($profile),
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
        } elseif (is_array($rawPayload) && isset($rawPayload['poll']) && is_array($rawPayload['poll'])) {
            $payload['poll'] = $rawPayload['poll'];
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
    public function chat(array $profile): array {
        $chatType = (string) ($profile['chat_type'] ?? 'private');
        $payload = [
            'id' => (int) ($profile['chat_id'] ?? 0),
            'type' => $chatType,
        ];

        if (in_array($chatType, ['group', 'supergroup', 'channel'], true)) {
            $title = trim((string) ($profile['chat_title'] ?? ''));
            $payload['title'] = $title === '' ? 'Chat ' . (string) ($profile['chat_id'] ?? '0') : $title;

            return $payload;
        }

        $payload['username'] = $profile['username'] ?? '';
        $payload['first_name'] = $profile['first_name'] ?? '';
        $payload['last_name'] = $profile['last_name'] ?? '';

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $storedMedia
     * @return list<array<string, mixed>>
     */
    public function photoSizes(string $photo, ?array $storedMedia = null): array {
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
     * @param array<string, mixed>|null $storedMedia
     * @return array<string, mixed>
     */
    public function document(string $document, ?array $storedMedia = null): array {
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
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $storedMedia
     * @return array<string, mixed>
     */
    public function typedMedia(string $mediaField, string $fileId, array $params, ?array $storedMedia = null): array {
        $payload = [
            'file_id' => $fileId,
            'file_unique_id' => $storedMedia['file_unique_id'] ?? substr(sha1($mediaField . ':' . $fileId), 0, 16),
        ];

        foreach ($this->typedMediaOptionalFields($mediaField) as $field => $type) {
            if (!isset($params[$field]) || trim((string) $params[$field]) === '') {
                continue;
            }

            $payload[$field] = $type === 'int'
                ? BotApiParams::int($params[$field], 0)
                : trim((string) $params[$field]);
        }

        if ($storedMedia === null && ($mediaField === 'audio' || $mediaField === 'document') && !isset($payload['file_name'])) {
            $path = parse_url($fileId, PHP_URL_PATH);
            $fileName = basename((string) ($path ?: $fileId));
            if ($fileName !== '') {
                $payload['file_name'] = $fileName;
            }
        }

        if ($storedMedia !== null) {
            $payload['file_size'] = $storedMedia['file_size'];
            if ($this->supportsStoredMimeType($mediaField) && !isset($payload['mime_type'])) {
                $payload['mime_type'] = $storedMedia['mime_type'];
            }
            if ($this->supportsStoredFileName($mediaField) && !isset($payload['file_name'])) {
                $payload['file_name'] = $storedMedia['file_name'];
            }
        }

        return $payload;
    }

    private function supportsStoredMimeType(string $mediaField): bool {
        return in_array($mediaField, ['video', 'animation', 'audio', 'voice'], true);
    }

    private function supportsStoredFileName(string $mediaField): bool {
        return in_array($mediaField, ['video', 'animation', 'audio'], true);
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

}

<?php

declare(strict_types=1);

namespace App;

/**
 * Готовит компактные блоки отображения structured/media payload для шаблона чата.
 */
final readonly class MessageRenderer {

    /**
     * @param array<string, mixed> $message
     * @return list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}>
     */
    public static function blocksFromMessage(array $message, ?MediaStorage $mediaStorage = null, string $botToken = ''): array {
        $payload = self::messagePayload($message);
        if ($payload === null) {
            return [];
        }

        $blocks = [];
        self::appendPhoto($blocks, $payload);
        self::appendDocument($blocks, $payload);
        self::appendLocation($blocks, $payload);
        self::appendVenue($blocks, $payload);
        self::appendContact($blocks, $payload);
        self::appendDice($blocks, $payload);
        self::appendPoll($blocks, $payload);
        self::appendTypedMedia($blocks, $payload);

        return self::withLocalMediaLinks($blocks, $mediaStorage, $botToken);
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>|null
     */
    private static function messagePayload(array $message): ?array {
        $rawPayload = json_decode((string) ($message['raw_payload'] ?? ''), true);
        if (!is_array($rawPayload)) {
            return null;
        }

        return isset($rawPayload['message']) && is_array($rawPayload['message'])
            ? $rawPayload['message']
            : $rawPayload;
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendPhoto(array &$blocks, array $payload): void {
        if (!isset($payload['photo']) || !is_array($payload['photo'])) {
            return;
        }

        $source = (string) ($payload['photo_source'] ?? '');
        if ($source === '' && isset($payload['photo'][0]) && is_array($payload['photo'][0])) {
            $source = (string) ($payload['photo'][0]['file_id'] ?? '');
        }

        $blocks[] = self::block('Photo', $source);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendDocument(array &$blocks, array $payload): void {
        if (!isset($payload['document']) || !is_array($payload['document'])) {
            return;
        }

        $source = (string) ($payload['document_source'] ?? $payload['document']['file_id'] ?? '');
        $blocks[] = self::block('Document', $source);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendLocation(array &$blocks, array $payload): void {
        if (!isset($payload['location']) || !is_array($payload['location'])) {
            return;
        }

        $blocks[] = self::block('Location', '', [
            (string) ($payload['location']['latitude'] ?? '') . ', ' . (string) ($payload['location']['longitude'] ?? ''),
        ]);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendVenue(array &$blocks, array $payload): void {
        if (!isset($payload['venue']) || !is_array($payload['venue'])) {
            return;
        }

        $lines = [
            (string) ($payload['venue']['title'] ?? ''),
            (string) ($payload['venue']['address'] ?? ''),
        ];
        if (isset($payload['venue']['location']) && is_array($payload['venue']['location'])) {
            $lines[] = (string) ($payload['venue']['location']['latitude'] ?? '')
                . ', '
                . (string) ($payload['venue']['location']['longitude'] ?? '');
        }

        $blocks[] = self::block('Venue', '', $lines);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendContact(array &$blocks, array $payload): void {
        if (!isset($payload['contact']) || !is_array($payload['contact'])) {
            return;
        }

        $blocks[] = self::block('Contact', '', [
            trim((string) ($payload['contact']['first_name'] ?? '') . ' ' . (string) ($payload['contact']['last_name'] ?? '')),
            (string) ($payload['contact']['phone_number'] ?? ''),
        ]);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendDice(array &$blocks, array $payload): void {
        if (!isset($payload['dice']) || !is_array($payload['dice'])) {
            return;
        }

        $blocks[] = self::block('Dice', '', [
            (string) ($payload['dice']['emoji'] ?? '') . ' · value ' . (string) ($payload['dice']['value'] ?? ''),
        ]);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendPoll(array &$blocks, array $payload): void {
        if (!isset($payload['poll']) || !is_array($payload['poll'])) {
            return;
        }

        $items = [];
        if (isset($payload['poll']['options']) && is_array($payload['poll']['options'])) {
            foreach ($payload['poll']['options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $items[] = (string) ($option['text'] ?? '') . ' (' . (string) ($option['voter_count'] ?? 0) . ')';
            }
        }

        $blocks[] = self::block('Poll', '', [
            (string) ($payload['poll']['question'] ?? ''),
            (string) ($payload['poll']['type'] ?? 'regular'),
        ], $items);
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @param array<string, mixed> $payload
     */
    private static function appendTypedMedia(array &$blocks, array $payload): void {
        $labels = [
            'video' => 'Video',
            'animation' => 'Animation',
            'audio' => 'Audio',
            'voice' => 'Voice',
            'video_note' => 'Video note',
            'sticker' => 'Sticker',
        ];

        foreach ($labels as $field => $label) {
            if (!isset($payload[$field]) || !is_array($payload[$field])) {
                continue;
            }

            $media = $payload[$field];
            $source = (string) ($payload[$field . '_source'] ?? $media['file_id'] ?? '');
            $lines = [];
            foreach (['duration', 'width', 'height', 'file_name', 'title', 'performer'] as $key) {
                if (isset($media[$key]) && (string) $media[$key] !== '') {
                    $lines[] = $key . ' ' . (string) $media[$key];
                }
            }

            $blocks[] = self::block($label, $source, $lines);
        }
    }

    /**
     * @param list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}> $blocks
     * @return list<array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}>
     */
    private static function withLocalMediaLinks(array $blocks, ?MediaStorage $mediaStorage, string $botToken): array {
        if ($mediaStorage === null || $botToken === '') {
            return $blocks;
        }

        foreach ($blocks as $index => $block) {
            $source = (string) ($block['source'] ?? '');
            if (!str_starts_with($source, 'local-media:')) {
                continue;
            }

            $file = $mediaStorage->findByFileId($source);
            if ($file === null) {
                continue;
            }

            $filePath = (string) $file['file_path'];
            $downloadUrl = '/file/bot' . $botToken . '/' . rawurlencode($filePath);
            $blocks[$index]['download_url'] = $downloadUrl;

            $resolvedPath = $mediaStorage->resolveDownloadPath($filePath);
            if ($resolvedPath !== null && str_starts_with($mediaStorage->contentType($resolvedPath), 'image/')) {
                $blocks[$index]['preview_url'] = $downloadUrl;
            }
        }

        return $blocks;
    }

    /**
     * @return array{title: string, source?: string, download_url?: string, preview_url?: string, lines?: list<string>, items?: list<string>}
     */
    private static function block(string $title, string $source = '', array $lines = [], array $items = []): array {
        $block = ['title' => $title];
        if ($source !== '') {
            $block['source'] = $source;
        }
        if ($lines !== []) {
            $block['lines'] = array_values(array_filter($lines, fn(string $line): bool => $line !== ''));
        }
        if ($items !== []) {
            $block['items'] = $items;
        }

        return $block;
    }
}

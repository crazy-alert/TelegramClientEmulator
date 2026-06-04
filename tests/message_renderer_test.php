<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/MediaStorage.php';
require dirname(__DIR__) . '/src/MessageRenderer.php';

use App\MediaStorage;
use App\MessageRenderer;

final class MessageRendererTestFailure extends RuntimeException {
}

function assertRendererSame(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        throw new MessageRendererTestFailure($message . "\nОжидалось: " . var_export($expected, true) . "\nПолучено: " . var_export($actual, true));
    }
}

$blocks = MessageRenderer::blocksFromMessage([
    'raw_payload' => json_encode([
        'message' => [
            'photo' => [
                [
                    'file_id' => 'photo-file-id',
                    'file_unique_id' => 'photo-unique',
                    'width' => 0,
                    'height' => 0,
                ],
            ],
            'caption' => 'Caption',
        ],
    ], JSON_THROW_ON_ERROR),
]);
assertRendererSame(
    [
        [
            'title' => 'Photo',
            'source' => 'photo-file-id',
        ],
    ],
    $blocks,
    'Renderer должен доставать photo из update envelope',
);

$blocks = MessageRenderer::blocksFromMessage([
    'raw_payload' => json_encode([
        'document' => [
            'file_id' => 'document-file-id',
            'file_unique_id' => 'document-unique',
            'file_name' => 'report.txt',
        ],
        'document_source' => 'local-media:abc',
    ], JSON_THROW_ON_ERROR),
]);
assertRendererSame(
    [
        [
            'title' => 'Document',
            'source' => 'local-media:abc',
        ],
    ],
    $blocks,
    'Renderer должен использовать явный document_source',
);

$blocks = MessageRenderer::blocksFromMessage([
    'raw_payload' => json_encode([
        'poll' => [
            'question' => 'Question',
            'type' => 'quiz',
            'options' => [
                ['text' => 'A', 'voter_count' => 0],
                ['text' => 'B', 'voter_count' => 2],
            ],
        ],
    ], JSON_THROW_ON_ERROR),
]);
assertRendererSame(
    [
        [
            'title' => 'Poll',
            'lines' => ['Question', 'quiz'],
            'items' => ['A (0)', 'B (2)'],
        ],
    ],
    $blocks,
    'Renderer должен нормализовать poll options',
);

$mediaDir = sys_get_temp_dir() . '/telegram-emulator-renderer-' . bin2hex(random_bytes(4));
$mediaStorage = new MediaStorage($mediaDir, 1024 * 1024);
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=') ?: '';
$storedMedia = $mediaStorage->storeUploadedFile([
    'name' => 'photo',
    'filename' => 'photo.png',
    'content_type' => 'image/png',
    'content' => $pngBytes,
    'size' => strlen($pngBytes),
]);
$blocks = MessageRenderer::blocksFromMessage([
    'raw_payload' => json_encode([
        'photo' => [
            [
                'file_id' => $storedMedia['file_id'],
                'file_unique_id' => $storedMedia['file_unique_id'],
                'width' => 0,
                'height' => 0,
            ],
        ],
        'photo_source' => $storedMedia['file_id'],
    ], JSON_THROW_ON_ERROR),
], $mediaStorage, '123456:local-dev-token');
assertRendererSame(
    [
        [
            'title' => 'Photo',
            'source' => $storedMedia['file_id'],
            'download_url' => '/file/bot123456:local-dev-token/' . rawurlencode($storedMedia['file_path']),
            'preview_url' => '/file/bot123456:local-dev-token/' . rawurlencode($storedMedia['file_path']),
        ],
    ],
    $blocks,
    'Renderer должен добавлять download и preview URL для локального image media',
);

echo "OK: message renderer tests passed\n";

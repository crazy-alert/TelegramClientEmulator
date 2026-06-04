<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Локальное хранилище файлов, загруженных через Bot API и UI.
 */
final readonly class MediaStorage {

    private const DEFAULT_MAX_BYTES = 10485760;

    public function __construct(
        private string $mediaDir,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {
    }

    /**
     * @param array{name: string, filename: string, content_type: string, content: string, size: int} $file
     * @return array{file_id: string, file_unique_id: string, file_name: string, mime_type: string, file_size: int, file_path: string}
     */
    public function storeUploadedFile(array $file): array {
        $size = (int) ($file['size'] ?? strlen((string) ($file['content'] ?? '')));
        if ($size <= 0) {
            throw new RuntimeException('Bad Request: uploaded file is empty');
        }

        if ($size > $this->maxBytes) {
            throw new RuntimeException('Bad Request: uploaded file is too large');
        }

        $originalName = $this->safeFileName((string) ($file['filename'] ?? 'file'));
        $mimeType = trim((string) ($file['content_type'] ?? 'application/octet-stream'));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $hash = hash('sha256', (string) $file['content']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $storedName = $hash . ($extension !== '' ? '.' . strtolower($extension) : '');
        $targetPath = $this->mediaDir . DIRECTORY_SEPARATOR . $storedName;

        $this->ensureMediaDir();
        if (!is_file($targetPath) && file_put_contents($targetPath, (string) $file['content'], LOCK_EX) === false) {
            throw new RuntimeException('Bad Request: failed to store uploaded file');
        }

        return [
            'file_id' => 'local-media:' . $hash,
            'file_unique_id' => substr($hash, 0, 16),
            'file_name' => $originalName,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'file_path' => $storedName,
        ];
    }

    public function maxBytes(): int {
        return $this->maxBytes;
    }

    public function path(): string {
        return $this->mediaDir;
    }

    private function ensureMediaDir(): void {
        if (is_dir($this->mediaDir)) {
            return;
        }

        if (!mkdir($this->mediaDir, 0777, true) && !is_dir($this->mediaDir)) {
            throw new RuntimeException('Bad Request: failed to prepare media storage');
        }
    }

    private function safeFileName(string $fileName): string {
        $fileName = str_replace('\\', '/', $fileName);
        $fileName = basename($fileName);
        $fileName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $fileName) ?? '';
        $fileName = trim($fileName, '._-');

        return $fileName === '' ? 'file' : substr($fileName, 0, 120);
    }
}

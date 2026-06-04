<?php

declare(strict_types=1);

namespace App;

/**
 * Парсит параметры HTTP body при отключенном enable_post_data_reading.
 */
final readonly class BotApiRequestParser {

    public const FILES_KEY = '_files';

    /**
     * @return array<string, mixed>|null
     */
    public function parse(string $method, string $rawBody, string $contentType): ?array {
        if ($method === 'GET' || $rawBody === '') {
            return null;
        }

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($rawBody, true);

            return is_array($data) ? $data : null;
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($rawBody, $data);

            return $data;
        }

        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->parseMultipartFormData($rawBody, $contentType);
        }

        return null;
    }

    /**
     * Парсит текстовые поля и файловые части multipart/form-data из raw body.
     *
     * @return array<string, mixed>
     */
    private function parseMultipartFormData(string $raw, string $contentType): array {
        if (preg_match('/boundary=(?:"([^"]+)"|([^;]+))/i', $contentType, $matches) !== 1) {
            return [];
        }

        $boundary = $matches[1] !== '' ? $matches[1] : trim($matches[2]);
        if ($boundary === '') {
            return [];
        }

        $fields = [];
        $files = [];
        $parts = explode('--' . $boundary, $raw);

        foreach ($parts as $part) {
            $part = preg_replace('/^\r?\n/', '', $part) ?? $part;
            if (str_ends_with($part, "\r\n")) {
                $part = substr($part, 0, -2);
            } elseif (str_ends_with($part, "\n")) {
                $part = substr($part, 0, -1);
            }

            if ($part === '' || $part === '--') {
                continue;
            }

            $separator = str_contains($part, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
            [$headers, $body] = array_pad(explode($separator, $part, 2), 2, '');

            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bname="([^"]+)"/i', $headers, $nameMatches) !== 1) {
                continue;
            }

            $name = $nameMatches[1];
            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bfilename="([^"]*)"/i', $headers, $fileNameMatches) === 1) {
                $fileName = $fileNameMatches[1];
                if ($fileName === '') {
                    continue;
                }

                $contentTypeHeader = 'application/octet-stream';
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $contentTypeMatches) === 1) {
                    $contentTypeHeader = trim($contentTypeMatches[1]);
                }

                $files[$name] = [
                    'name' => $name,
                    'filename' => $fileName,
                    'content_type' => $contentTypeHeader,
                    'content' => $body,
                    'size' => strlen($body),
                ];
                continue;
            }

            $fields[$name] = $body;
        }

        if ($files !== []) {
            $fields[self::FILES_KEY] = $files;
        }

        return $fields;
    }
}

<?php

declare(strict_types=1);

namespace App;

/**
 * Парсит параметры HTTP body при отключенном enable_post_data_reading.
 */
final readonly class BotApiRequestParser {

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
     * Парсит только текстовые поля multipart/form-data; файловые части игнорируются.
     *
     * @return array<string, string>
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
        $parts = explode('--' . $boundary, $raw);

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            $part = preg_replace("/\r\n--\r\n?$/", '', $part) ?? $part;
            $part = rtrim($part, "\r\n");

            if ($part === '' || $part === '--') {
                continue;
            }

            $separator = str_contains($part, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
            [$headers, $body] = array_pad(explode($separator, $part, 2), 2, '');

            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bname="([^"]+)"/i', $headers, $nameMatches) !== 1) {
                continue;
            }

            if (preg_match('/Content-Disposition:\s*form-data\b[^\r\n]*\bfilename="/i', $headers) === 1) {
                continue;
            }

            $fields[$nameMatches[1]] = $body;
        }

        return $fields;
    }
}

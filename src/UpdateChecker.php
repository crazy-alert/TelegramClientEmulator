<?php

declare(strict_types=1);

namespace App;

final readonly class UpdateChecker {

    public function __construct(
        private string $versionFile,
        private ?string $updateCheckUrl = null,
    ) {
    }

    /**
     * @return array{
     *     ok: bool,
     *     current_commit: string,
     *     latest_commit: string|null,
     *     update_available: bool,
     *     source_url: string,
     *     latest_url: string|null,
     *     error: string|null
     * }
     */
    public function check(): array {
        $local = $this->localVersion();
        $currentCommit = (string) ($local['commit'] ?? '');
        $sourceUrl = $this->updateCheckUrl
            ?? getenv('TELEGRAM_EMULATOR_UPDATE_CHECK_URL')
            ?: (string) ($local['update_check_url'] ?? '');

        if ($currentCommit === '') {
            return $this->failed($currentCommit, $sourceUrl, 'В локальном version.json не указан commit.');
        }

        if ($sourceUrl === '') {
            return $this->failed($currentCommit, $sourceUrl, 'Не указан URL проверки обновлений.');
        }

        $remote = $this->fetchJson($sourceUrl);
        if (!is_array($remote)) {
            return $this->failed($currentCommit, $sourceUrl, 'Не удалось получить информацию об обновлениях.');
        }

        $latestCommit = $this->remoteCommit($remote);
        if ($latestCommit === '') {
            return $this->failed($currentCommit, $sourceUrl, 'Ответ сервера обновлений не содержит commit hash.');
        }

        return [
            'ok' => true,
            'current_commit' => $currentCommit,
            'latest_commit' => $latestCommit,
            'update_available' => $latestCommit !== $currentCommit,
            'source_url' => $sourceUrl,
            'latest_url' => $this->remoteHtmlUrl($remote),
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function localVersion(): array {
        if (!is_file($this->versionFile)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->versionFile), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url): ?array {
        if (!filter_var($url, FILTER_VALIDATE_URL) && !is_file($url)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'User-Agent: TelegramClientEmulator update checker',
                    'Accept: application/json',
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function remoteCommit(array $remote): string {
        $sha = $remote['sha'] ?? $remote['commit'] ?? null;

        return is_string($sha) ? trim($sha) : '';
    }

    /**
     * @param array<string, mixed> $remote
     */
    private function remoteHtmlUrl(array $remote): ?string {
        $url = $remote['html_url'] ?? $remote['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array{
     *     ok: false,
     *     current_commit: string,
     *     latest_commit: null,
     *     update_available: false,
     *     source_url: string,
     *     latest_url: null,
     *     error: string
     * }
     */
    private function failed(string $currentCommit, string $sourceUrl, string $error): array {
        return [
            'ok' => false,
            'current_commit' => $currentCommit,
            'latest_commit' => null,
            'update_available' => false,
            'source_url' => $sourceUrl,
            'latest_url' => null,
            'error' => $error,
        ];
    }
}

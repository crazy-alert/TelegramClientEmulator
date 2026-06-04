<?php

declare(strict_types=1);

namespace App;

/**
 * Выбирает и нормализует updates для метода `getUpdates`.
 */
final readonly class LongPollingService {

    public function __construct(private UpdateRepository $updates) {
    }

    /**
     * @param list<string>|null $allowedUpdates
     * @return list<array<string, mixed>>
     */
    public function result(int $botId, int $offset, int $limit, ?array $allowedUpdates): array {
        $updates = $this->pendingUpdates($botId, $offset, max(1, min(100, $limit)));

        return $this->updatesResult($updates, $allowedUpdates);
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

}

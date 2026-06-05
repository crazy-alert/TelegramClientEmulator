<h1>Request inspector</h1>

<?php
$prettyJson = static function (?string $value): string {
    $value = (string) ($value ?? '');
    $decoded = json_decode($value, true);

    return is_array($decoded)
        ? (json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: $value)
        : $value;
};
?>

<section class="panel" style="margin-bottom: 18px;">
    <h2>Что показывает Inspector</h2>
    <p class="muted">
        Inspector читает локальные HTTP JSONL-логи Bot API и webhook delivery attempts.
        Если бот еще не делал запросы к локальному Bot API и webhook-доставок не было,
        ниже будут только фильтры и пустые состояния.
    </p>
</section>

<form class="editor" method="get" action="/request-inspector" style="margin-bottom: 18px;">
    <div class="grid">
        <label>
            Bot token
            <input name="token" value="" placeholder="<?= ($hasTokenFilter ?? false) ? 'Фильтр по token применен' : '123456:local-dev-token' ?>">
        </label>

        <label>
            Bot API method
            <input name="method" value="<?= e($selectedMethod ?? '') ?>" placeholder="sendMessage">
        </label>

        <label>
            HTTP status
            <input name="response_status" value="<?= e($selectedResponseStatus ?? '') ?>" placeholder="400">
        </label>

        <label style="display: flex; align-items: center; gap: 8px; margin-top: 28px;">
            <input type="checkbox" name="ok_false" value="1" <?= ($onlyOkFalse ?? false) ? 'checked' : '' ?>>
            Только ok=false / ошибки
        </label>
    </div>
    <div class="actions">
        <button type="submit">Фильтровать</button>
        <a class="button secondary" href="/request-inspector">Сбросить</a>
    </div>
</form>

<section class="panel" style="margin-bottom: 18px;">
    <h2>Bot API request/response</h2>
    <p class="muted">
        События читаются из HTTP JSONL-логов. Bot token и headers с token маскируются в выводе.
    </p>

    <?php if ($botApiEvents === []): ?>
        <div class="empty">Bot API requests пока не найдены.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Время</th>
                <th>Метод</th>
                <th>Request</th>
                <th>Response</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($botApiEvents as $event): ?>
                <tr>
                    <td>
                        <?= e($event['timestamp']) ?><br>
                        <span class="muted"><?= e($event['duration_ms']) ?> ms</span>
                    </td>
                    <td>
                        <code><?= e($event['bot_api_method']) ?></code><br>
                        <span class="muted"><?= e($event['bot_token']) ?></span>
                    </td>
                    <td>
                        <code><?= e($event['request_method']) ?> <?= e($event['uri']) ?></code>
                        <details style="margin-top: 8px;">
                            <summary>Headers</summary>
                            <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e(json_encode($event['request_headers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '') ?></code></pre>
                        </details>
                        <details style="margin-top: 8px;">
                            <summary>curl</summary>
                            <pre class="copy-friendly" style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($event['curl'] ?? '') ?></code></pre>
                        </details>
                        <details style="margin-top: 8px;">
                            <summary>Body pretty JSON</summary>
                            <pre class="json-pretty" style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($event['request_body_pretty'] ?? $event['request_body']) ?></code></pre>
                        </details>
                    </td>
                    <td>
                        HTTP <?= e($event['response_status']) ?>
                        <?php if (($event['response_ok'] ?? null) === false): ?>
                            <span class="muted">ok=false</span>
                        <?php endif; ?>
                        <details style="margin-top: 8px;">
                            <summary>Headers</summary>
                            <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e(json_encode($event['response_headers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '') ?></code></pre>
                        </details>
                        <details style="margin-top: 8px;">
                            <summary>Body pretty JSON</summary>
                            <pre class="json-pretty" style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($event['response_body_pretty'] ?? $event['response_body']) ?></code></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Webhook delivery request/response</h2>
    <p class="muted">
        Последние webhook delivery attempts берутся из локального хранилища; secret token в request headers маскируется.
    </p>

    <?php if ($webhookAttempts === []): ?>
        <div class="empty">Webhook delivery attempts пока нет.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Время</th>
                <th>Update</th>
                <th>Webhook</th>
                <th>Response</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($webhookAttempts as $attempt): ?>
                <tr>
                    <td><?= e($attempt['created_at']) ?></td>
                    <td>
                        <code><?= e($attempt['update_id']) ?></code><br>
                        <span class="muted"><?= e($attempt['queue_state']) ?></span>
                        <div style="margin-top: 6px;">
                            <a href="/updates?update_id=<?= e($attempt['update_id']) ?>">Update</a>
                            <span class="muted">·</span>
                            <a href="/delivery-attempts?update_id=<?= e($attempt['update_id']) ?>">Attempts</a>
                        </div>
                    </td>
                    <td>
                        <code><?= e($attempt['webhook_url']) ?></code>
                        <details style="margin-top: 8px;">
                            <summary>Request headers</summary>
                            <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($attempt['request_headers'] ?? '') ?></code></pre>
                        </details>
                        <details style="margin-top: 8px;">
                            <summary>Request body pretty JSON</summary>
                            <pre class="json-pretty" style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($prettyJson($attempt['request_body'] ?? '')) ?></code></pre>
                        </details>
                    </td>
                    <td>
                        HTTP <?= e($attempt['response_status'] ?? '') ?><br>
                        <span class="muted"><?= e($attempt['duration_ms'] ?? '') ?> ms</span>
                        <details style="margin-top: 8px;">
                            <summary>Response body pretty JSON</summary>
                            <pre class="json-pretty" style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($prettyJson($attempt['response_body'] ?? '')) ?></code></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

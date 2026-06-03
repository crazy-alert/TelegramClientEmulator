<h1>Webhook delivery attempts</h1>

<form class="editor" method="get" action="/delivery-attempts" style="margin-bottom: 18px;">
    <div class="grid">
        <label>
            Бот
            <select name="bot_id">
                <option value="">Все боты</option>
                <?php foreach ($allBots as $availableBot): ?>
                    <option value="<?= e($availableBot['id']) ?>" <?= (int) ($selectedBotId ?? 0) === (int) $availableBot['id'] ? 'selected' : '' ?>>
                        @<?= e($availableBot['username']) ?> · <?= e($availableBot['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            update_id
            <input type="number" name="update_id" value="<?= (int) ($selectedUpdateId ?? 0) > 0 ? e($selectedUpdateId) : '' ?>" placeholder="100000001">
        </label>
    </div>
    <div class="actions">
        <button type="submit">Фильтровать</button>
        <a class="button secondary" href="/delivery-attempts">Сбросить</a>
    </div>
</form>

<?php if ($attempts === []): ?>
    <div class="empty">Попыток webhook-доставки пока нет.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Update</th>
            <th>Бот</th>
            <th>Пользователь</th>
            <th>Webhook URL</th>
            <th>HTTP</th>
            <th>Ошибка</th>
            <th>Детали</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($attempts as $attempt): ?>
            <tr>
                <td>
                    <code>#<?= e($attempt['id']) ?></code><br>
                    <span class="muted"><?= e($attempt['created_at']) ?></span>
                </td>
                <td>
                    <code><?= e($attempt['update_id']) ?></code><br>
                    <span class="muted"><?= e($attempt['queue_state']) ?></span>
                </td>
                <td>
                    @<?= e($attempt['bot_username']) ?><br>
                    <span class="muted"><?= e($attempt['bot_display_name']) ?></span>
                </td>
                <td>
                    @<?= e($attempt['profile_username']) ?><br>
                    <span class="muted"><?= e($attempt['profile_first_name']) ?> <?= e($attempt['profile_last_name'] ?? '') ?></span>
                </td>
                <td><code><?= e($attempt['webhook_url']) ?></code></td>
                <td>
                    <?= e($attempt['response_status'] ?? '') ?><br>
                    <span class="muted"><?= e($attempt['duration_ms'] ?? '') ?> ms</span>
                </td>
                <td><?= e($attempt['error'] ?? '') ?></td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/chat?profile_id=<?= e($attempt['profile_id']) ?>&bot_id=<?= e($attempt['bot_id']) ?>">Чат</a>
                    </div>
                    <details style="margin-top: 8px;">
                        <summary>Request</summary>
                        <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($attempt['request_body']) ?></code></pre>
                    </details>
                    <details style="margin-top: 8px;">
                        <summary>Response</summary>
                        <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($attempt['response_body'] ?? '') ?></code></pre>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h1>Updates</h1>

<form class="editor" method="get" action="/updates" style="margin-bottom: 18px;">
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
            Пользователь
            <select name="profile_id">
                <option value="">Все пользователи</option>
                <?php foreach ($allUsers as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= (int) ($selectedProfileId ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        @<?= e($user['username']) ?> · <?= e($user['first_name']) ?> <?= e($user['last_name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            queue_state
            <select name="queue_state">
                <option value="">Все состояния</option>
                <?php foreach (['pending', 'delivered', 'confirmed', 'failed'] as $state): ?>
                    <option value="<?= e($state) ?>" <?= (string) ($selectedQueueState ?? '') === $state ? 'selected' : '' ?>>
                        <?= e($state) ?>
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
        <a class="button secondary" href="/updates">Сбросить</a>
    </div>
</form>

<?php if ((int) ($selectedBotId ?? 0) > 0): ?>
    <form class="editor" method="post" action="/updates/clear" onsubmit="return confirm('Удалить pending и confirmed updates выбранного бота?');" style="margin-bottom: 18px;">
        <input type="hidden" name="bot_id" value="<?= e($selectedBotId) ?>">
        <input type="hidden" name="confirm_clear" value="1">
        <div class="actions">
            <button type="submit" class="danger">Очистить pending/confirmed updates выбранного бота</button>
        </div>
    </form>
<?php endif; ?>

<?php if ($updates === []): ?>
    <div class="empty">Updates пока нет.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Update</th>
            <th>Бот</th>
            <th>Пользователь</th>
            <th>Доставка</th>
            <th>Время</th>
            <th>Детали</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($updates as $update): ?>
            <tr>
                <td><code>#<?= e($update['id']) ?></code></td>
                <td>
                    <code><?= e($update['update_id']) ?></code><br>
                    <span class="muted"><?= e($update['queue_state']) ?></span>
                </td>
                <td>
                    @<?= e($update['bot_username']) ?><br>
                    <span class="muted"><?= e($update['bot_display_name']) ?></span>
                </td>
                <td>
                    @<?= e($update['profile_username']) ?><br>
                    <span class="muted">
                        <?= e($update['profile_first_name']) ?> <?= e($update['profile_last_name'] ?? '') ?>
                        · <?= e($update['profile_chat_type']) ?> #<?= e($update['profile_chat_id']) ?>
                    </span>
                </td>
                <td><?= e($update['delivery_mode']) ?></td>
                <td>
                    <?= e($update['created_at']) ?><br>
                    <span class="muted">
                        delivered: <?= e($update['delivered_at'] ?? '') ?><br>
                        confirmed: <?= e($update['confirmed_at'] ?? '') ?>
                    </span>
                </td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/chat?profile_id=<?= e($update['profile_id']) ?>&amp;bot_id=<?= e($update['bot_id']) ?>">Чат</a>
                        <a class="button secondary" href="/delivery-attempts?bot_id=<?= e($update['bot_id']) ?>&amp;update_id=<?= e($update['update_id']) ?>">Attempts</a>
                    </div>
                    <details style="margin-top: 8px;">
                        <summary>Payload</summary>
                        <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($update['payload']) ?></code></pre>
                    </details>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

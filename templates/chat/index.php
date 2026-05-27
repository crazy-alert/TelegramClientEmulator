<h1>Чат</h1>

<form class="editor" method="get" action="/chat" style="margin-bottom: 18px;">
    <div class="grid">
        <label>
            Пользователь
            <select name="profile_id" required>
                <option value="">Выберите пользователя</option>
                <?php foreach ($allUsers as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= (int) ($selectedProfileId ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        @<?= e($user['username']) ?> · <?= e($user['first_name']) ?> <?= e($user['last_name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Бот
            <select name="bot_id" required>
                <option value="">Выберите бота</option>
                <?php foreach ($allBots as $availableBot): ?>
                    <option value="<?= e($availableBot['id']) ?>" <?= (int) ($selectedBotId ?? 0) === (int) $availableBot['id'] ? 'selected' : '' ?>>
                        @<?= e($availableBot['username']) ?> · <?= e($availableBot['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="actions">
        <button type="submit">Открыть чат</button>
    </div>
</form>

<?php if ($profile === null || $bot === null): ?>
    <div class="empty">
        Выберите сохраненного пользователя и бота, чтобы открыть диалог.
    </div>
<?php else: ?>
    <div class="panel" style="margin-bottom: 18px;">
        <strong>Пользователь:</strong> @<?= e($profile['username']) ?>
        (<?= e($profile['first_name']) ?> <?= e($profile['last_name'] ?? '') ?>,
        ID: <?= e($profile['user_id']) ?>,
        чат: <?= e($profile['chat_type']) ?> #<?= e($profile['chat_id']) ?>)
        &nbsp;·&nbsp;
        <strong>Бот:</strong> <?= e($bot['display_name']) ?>
        (@<?= e($bot['username']) ?>,
        режим: <?= e($bot['delivery_mode']) ?>,
        очередь Long Polling: <?= e($pendingUpdateCount ?? 0) ?>)
    </div>

    <!-- История сообщений -->
    <div class="panel" style="margin-bottom: 18px; min-height: 200px; max-height: 500px; overflow-y: auto;">
        <?php if ($messages === []): ?>
            <p class="muted">Диалог пуст. Отправьте первое сообщение.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e6edf2;">
                    <div style="margin-bottom: 4px;">
                        <strong style="color: <?= $msg['direction'] === 'user' ? '#2481cc' : '#4caf50' ?>">
                            <?= $msg['direction'] === 'user' ? '👤 Пользователь' : '🤖 Бот' ?>
                        </strong>
                        <span class="muted" style="font-size: 12px;">
                            #<?= e($msg['telegram_message_id']) ?>
                            · <?= e($msg['created_at']) ?>
                        </span>
                    </div>
                    <div style="white-space: pre-wrap;"><?= e($msg['text']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Поле ввода -->
    <form class="editor" method="post" action="/chat/send" style="margin-bottom: 18px;">
        <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
        <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
        <label>
            Сообщение
            <textarea name="text" rows="3" required
                placeholder="Введите сообщение (например, /start)..."
                style="width: 100%; resize: vertical; font: inherit; padding: 8px 10px; border: 1px solid #c8d3dc; border-radius: 6px;"
            ></textarea>
        </label>
        <div class="actions">
            <button type="submit">Отправить</button>
        </div>
    </form>

    <!-- Инспектор последнего Update -->
    <?php if ($latestUpdate !== null): ?>
        <section class="panel">
            <h2>📦 Последний Update (inspector)</h2>
            <table>
                <tr>
                    <th>update_id</th>
                    <td><code><?= e($latestUpdate['update_id']) ?></code></td>
                </tr>
                <tr>
                    <th>queue_state</th>
                    <td><?= e($latestUpdate['queue_state']) ?></td>
                </tr>
                <tr>
                    <th>delivery_mode</th>
                    <td><?= e($latestUpdate['delivery_mode']) ?></td>
                </tr>
                <tr>
                    <th>created_at</th>
                    <td><?= e($latestUpdate['created_at']) ?></td>
                </tr>
            </table>
            <details style="margin-top: 12px;">
                <summary>Raw payload (JSON)</summary>
                <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($latestUpdate['payload']) ?></code></pre>
            </details>
            <?php if ($latestDeliveryAttempt !== null): ?>
                <h3>Webhook delivery</h3>
                <table>
                    <tr>
                        <th>URL</th>
                        <td><code><?= e($latestDeliveryAttempt['webhook_url']) ?></code></td>
                    </tr>
                    <tr>
                        <th>HTTP status</th>
                        <td><?= e($latestDeliveryAttempt['response_status'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>duration_ms</th>
                        <td><?= e($latestDeliveryAttempt['duration_ms'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>error</th>
                        <td><?= e($latestDeliveryAttempt['error'] ?? '') ?></td>
                    </tr>
                </table>
                <details style="margin-top: 12px;">
                    <summary>Webhook response body</summary>
                    <pre style="background: #f4f7f9; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px;"><code><?= e($latestDeliveryAttempt['response_body'] ?? '') ?></code></pre>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

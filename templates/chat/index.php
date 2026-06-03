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
    <?php
    $replyKeyboard = null;
    for ($i = count($messages) - 1; $i >= 0; $i--) {
        if (($messages[$i]['direction'] ?? '') !== 'bot') {
            continue;
        }

        $rawPayload = json_decode((string) ($messages[$i]['raw_payload'] ?? ''), true);
        $markup = is_array($rawPayload) && isset($rawPayload['reply_markup']) && is_array($rawPayload['reply_markup'])
            ? $rawPayload['reply_markup']
            : null;

        if ($markup !== null && !empty($markup['remove_keyboard'])) {
            $replyKeyboard = null;
            break;
        }

        if ($markup !== null && isset($markup['keyboard']) && is_array($markup['keyboard'])) {
            $replyKeyboard = $markup['keyboard'];
            break;
        }
    }

    $renderMessageText = static function (string $text) use ($profile, $bot): void {
        $offset = 0;
        preg_match_all('/(?<!\S)(\/[A-Za-z0-9_]{1,32}(?:@[A-Za-z0-9_]+)?)/u', $text, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $match) {
            [$command, $position] = $match;
            echo e(substr($text, $offset, $position - $offset));
            echo '<form class="message-command" method="post" action="/chat/send" style="display: inline;">';
            echo '<input type="hidden" name="profile_id" value="' . e($profile['id']) . '">';
            echo '<input type="hidden" name="bot_id" value="' . e($bot['id']) . '">';
            echo '<input type="hidden" name="text" value="' . e($command) . '">';
            echo '<button type="submit" class="secondary" style="display: inline; min-height: 0; padding: 0; border: 0; background: transparent; color: #2481cc; vertical-align: baseline;">' . e($command) . '</button>';
            echo '</form>';
            $offset = $position + strlen($command);
        }

        echo e(substr($text, $offset));
    };
    ?>
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

    <?php if (($botCommands ?? []) !== []): ?>
        <div class="panel" style="margin-bottom: 18px;">
            <h2>Команды бота</h2>
            <form method="post" action="/chat/send" style="display: flex; gap: 8px; align-items: end; margin-bottom: 12px;">
                <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                <label style="flex: 1; margin-bottom: 0;">
                    Выберите команду
                    <select name="text" required>
                        <?php foreach ($botCommands as $command): ?>
                            <option value="/<?= e($command['command']) ?>">
                                /<?= e($command['command']) ?> — <?= e($command['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit">Отправить</button>
            </form>
            <div class="actions">
                <?php foreach ($botCommands as $command): ?>
                    <form method="post" action="/chat/send">
                        <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                        <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                        <input type="hidden" name="text" value="/<?= e($command['command']) ?>">
                        <button type="submit" class="secondary" title="<?= e($command['description']) ?>">
                            /<?= e($command['command']) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- История сообщений -->
    <div class="panel" style="margin-bottom: 18px; min-height: 200px; max-height: 500px; overflow-y: auto;">
        <?php if ($messages === []): ?>
            <p class="muted">Диалог пуст. Отправьте первое сообщение.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $rawPayload = json_decode((string) ($msg['raw_payload'] ?? ''), true);
                $replyMarkup = is_array($rawPayload) && isset($rawPayload['reply_markup']) && is_array($rawPayload['reply_markup'])
                    ? $rawPayload['reply_markup']
                    : null;
                ?>
                <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #e6edf2;">
                    <div style="margin-bottom: 4px;">
                        <strong style="color: <?= $msg['direction'] === 'user' ? '#2481cc' : '#4caf50' ?>">
                            <?= $msg['direction'] === 'user' ? 'Пользователь' : 'Бот' ?>
                        </strong>
                        <span class="muted" style="font-size: 12px;">
                            #<?= e($msg['telegram_message_id']) ?>
                            · <?= e($msg['created_at']) ?>
                        </span>
                    </div>
                    <div style="white-space: pre-wrap;"><?php $renderMessageText((string) $msg['text']); ?></div>
                    <?php if ($msg['direction'] === 'bot' && $replyMarkup !== null && isset($replyMarkup['inline_keyboard']) && is_array($replyMarkup['inline_keyboard'])): ?>
                        <div style="display: grid; gap: 6px; margin-top: 10px; max-width: 420px;">
                            <?php foreach ($replyMarkup['inline_keyboard'] as $row): ?>
                                <?php if (!is_array($row)) { continue; } ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php foreach ($row as $button): ?>
                                        <?php if (!is_array($button)) { continue; } ?>
                                        <?php if (isset($button['callback_data'])): ?>
                                            <form method="post" action="/chat/callback">
                                                <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                                                <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                                                <input type="hidden" name="message_id" value="<?= e($msg['id']) ?>">
                                                <input type="hidden" name="callback_data" value="<?= e($button['callback_data']) ?>">
                                                <button type="submit" class="secondary"><?= e($button['text'] ?? $button['callback_data']) ?></button>
                                            </form>
                                        <?php elseif (isset($button['url'])): ?>
                                            <a class="button secondary" href="<?= e($button['url']) ?>" target="_blank" rel="noreferrer">
                                                <?= e($button['text'] ?? $button['url']) ?>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" class="secondary" disabled><?= e($button['text'] ?? 'Кнопка') ?></button>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if (is_array($replyKeyboard)): ?>
        <div class="panel" style="margin-bottom: 18px;">
            <h2>Клавиатура</h2>
            <div style="display: grid; gap: 8px; max-width: 520px;">
                <?php foreach ($replyKeyboard as $row): ?>
                    <?php if (!is_array($row)) { continue; } ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php foreach ($row as $button): ?>
                            <?php
                            $buttonText = is_array($button) ? (string) ($button['text'] ?? '') : (string) $button;
                            if ($buttonText === '') {
                                continue;
                            }
                            ?>
                            <form method="post" action="/chat/send">
                                <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                                <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                                <input type="hidden" name="text" value="<?= e($buttonText) ?>">
                                <button type="submit" class="secondary"><?= e($buttonText) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

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
            <h2>Последний Update (inspector)</h2>
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
                <?php if (($latestUpdate['queue_state'] ?? '') === 'failed'): ?>
                    <form method="post" action="/updates/<?= e($latestUpdate['id']) ?>/resend" style="margin-top: 12px;">
                        <button type="submit" class="secondary">Повторить webhook-доставку</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

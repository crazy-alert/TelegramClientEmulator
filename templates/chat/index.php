<?php $chatFragment = (bool) ($chatFragment ?? false); ?>

<?php if (!$chatFragment): ?>
<h1>Чат</h1>

<form class="editor" method="get" action="/chat" style="margin-bottom: 18px;">
    <div class="grid">
        <label>
            Пользователь / отправитель
            <select name="profile_id" required>
                <option value="">Выберите отправителя</option>
                <?php foreach ($allUsers as $user): ?>
                    <option value="<?= e($user['id']) ?>" <?= (int) ($selectedProfileId ?? 0) === (int) $user['id'] ? 'selected' : '' ?>>
                        @<?= e($user['username']) ?> · <?= e($user['first_name']) ?> <?= e($user['last_name'] ?? '') ?>
                        · <?= e($user['chat_type']) ?> #<?= e($user['chat_id']) ?>
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
<?php endif; ?>

<?php if ($profile === null || $bot === null): ?>
    <div class="empty">
        Выберите сохраненного пользователя и бота, чтобы открыть диалог.
    </div>
<?php else: ?>
    <?php
    $replyKeyboard = \App\ReplyMarkup::latestKeyboard($messages);

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
    <?php if (!$chatFragment): ?>
    <div class="panel" style="margin-bottom: 12px;">
        <strong>Пользователь:</strong> @<?= e($profile['username']) ?>
        (<?= e($profile['first_name']) ?> <?= e($profile['last_name'] ?? '') ?>,
        ID: <?= e($profile['user_id']) ?>,
        чат: <?= e($profile['chat_type']) ?> #<?= e($profile['chat_id']) ?>)
        &nbsp;·&nbsp;
        <strong>Бот:</strong> <?= e($bot['display_name']) ?>
        (@<?= e($bot['username']) ?>,
        режим: <?= e($bot['delivery_mode']) ?>,
        очередь Long Polling: <?= e($pendingUpdateCount ?? 0) ?>)
        <form method="post" action="/chat/clear" onsubmit="return confirm('Очистить историю и updates только этого диалога?');" style="margin-top: 12px;">
            <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
            <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
            <input type="hidden" name="confirm_clear" value="1">
            <button type="submit" class="danger">Очистить диалог</button>
        </form>
    </div>

        <div
            id="chat-live"
            hx-get="/chat/fragment?profile_id=<?= e($profile['id']) ?>&amp;bot_id=<?= e($bot['id']) ?>"
            hx-trigger="every 3s"
            hx-swap="innerHTML"
        >
    <?php endif; ?>

    <!-- История сообщений -->
    <div id="chat-messages" class="panel chat-messages" data-chat-messages style="margin-bottom: 18px; min-height: 200px; max-height: 750px; overflow-y: auto;">
        <?php if ($messages === []): ?>
            <p class="muted">Диалог пуст. Отправьте первое сообщение.</p>
        <?php else: ?>
            <?php foreach ($messages as $msg): ?>
                <?php
                $rawPayload = json_decode((string) ($msg['raw_payload'] ?? ''), true);
                $messagePayload = is_array($rawPayload) && isset($rawPayload['message']) && is_array($rawPayload['message'])
                    ? $rawPayload['message']
                    : $rawPayload;
                $replyMarkup = \App\ReplyMarkup::fromMessage($msg);
                $photoPayload = is_array($messagePayload) && isset($messagePayload['photo']) && is_array($messagePayload['photo'])
                    ? $messagePayload['photo']
                    : null;
                $photoSource = is_array($messagePayload) ? (string) ($messagePayload['photo_source'] ?? '') : '';
                if ($photoSource === '' && is_array($photoPayload) && isset($photoPayload[0]) && is_array($photoPayload[0])) {
                    $photoSource = (string) ($photoPayload[0]['file_id'] ?? '');
                }
                $documentPayload = is_array($messagePayload) && isset($messagePayload['document']) && is_array($messagePayload['document'])
                    ? $messagePayload['document']
                    : null;
                $documentSource = is_array($messagePayload) ? (string) ($messagePayload['document_source'] ?? '') : '';
                if ($documentSource === '' && is_array($documentPayload)) {
                    $documentSource = (string) ($documentPayload['file_id'] ?? '');
                }
                $locationPayload = is_array($messagePayload) && isset($messagePayload['location']) && is_array($messagePayload['location'])
                    ? $messagePayload['location']
                    : null;
                $venuePayload = is_array($messagePayload) && isset($messagePayload['venue']) && is_array($messagePayload['venue'])
                    ? $messagePayload['venue']
                    : null;
                $contactPayload = is_array($messagePayload) && isset($messagePayload['contact']) && is_array($messagePayload['contact'])
                    ? $messagePayload['contact']
                    : null;
                $dicePayload = is_array($messagePayload) && isset($messagePayload['dice']) && is_array($messagePayload['dice'])
                    ? $messagePayload['dice']
                    : null;
                $pollPayload = is_array($messagePayload) && isset($messagePayload['poll']) && is_array($messagePayload['poll'])
                    ? $messagePayload['poll']
                    : null;
                $typedMediaLabels = [
                    'video' => 'Video',
                    'animation' => 'Animation',
                    'audio' => 'Audio',
                    'voice' => 'Voice',
                    'video_note' => 'Video note',
                    'sticker' => 'Sticker',
                ];
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
                    <?php if ($photoPayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Photo</strong>
                            <?php if ($photoSource !== ''): ?>
                                <div class="muted" style="overflow-wrap: anywhere;"><code><?= e($photoSource) ?></code></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($documentPayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Document</strong>
                            <?php if ($documentSource !== ''): ?>
                                <div class="muted" style="overflow-wrap: anywhere;"><code><?= e($documentSource) ?></code></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($locationPayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Location</strong>
                            <div class="muted">
                                <?= e((string) ($locationPayload['latitude'] ?? '')) ?>,
                                <?= e((string) ($locationPayload['longitude'] ?? '')) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($venuePayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Venue</strong>
                            <div><?= e((string) ($venuePayload['title'] ?? '')) ?></div>
                            <div class="muted"><?= e((string) ($venuePayload['address'] ?? '')) ?></div>
                            <?php if (isset($venuePayload['location']) && is_array($venuePayload['location'])): ?>
                                <div class="muted">
                                    <?= e((string) ($venuePayload['location']['latitude'] ?? '')) ?>,
                                    <?= e((string) ($venuePayload['location']['longitude'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($contactPayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Contact</strong>
                            <div>
                                <?= e((string) ($contactPayload['first_name'] ?? '')) ?>
                                <?= e((string) ($contactPayload['last_name'] ?? '')) ?>
                            </div>
                            <div class="muted"><?= e((string) ($contactPayload['phone_number'] ?? '')) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($dicePayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Dice</strong>
                            <div class="muted">
                                <?= e((string) ($dicePayload['emoji'] ?? '')) ?>
                                · value <?= e((string) ($dicePayload['value'] ?? '')) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ($pollPayload !== null): ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong>Poll</strong>
                            <div><?= e((string) ($pollPayload['question'] ?? '')) ?></div>
                            <div class="muted"><?= e((string) ($pollPayload['type'] ?? 'regular')) ?></div>
                            <?php if (isset($pollPayload['options']) && is_array($pollPayload['options'])): ?>
                                <ol style="margin: 8px 0 0 18px; padding: 0;">
                                    <?php foreach ($pollPayload['options'] as $option): ?>
                                        <?php if (!is_array($option)) { continue; } ?>
                                        <li>
                                            <?= e((string) ($option['text'] ?? '')) ?>
                                            <span class="muted">(<?= e((string) ($option['voter_count'] ?? 0)) ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($typedMediaLabels as $mediaField => $mediaLabel): ?>
                        <?php
                        $typedMediaPayload = is_array($messagePayload) && isset($messagePayload[$mediaField]) && is_array($messagePayload[$mediaField])
                            ? $messagePayload[$mediaField]
                            : null;
                        if ($typedMediaPayload === null) {
                            continue;
                        }
                        $typedMediaSource = is_array($messagePayload) ? (string) ($messagePayload[$mediaField . '_source'] ?? '') : '';
                        if ($typedMediaSource === '') {
                            $typedMediaSource = (string) ($typedMediaPayload['file_id'] ?? '');
                        }
                        ?>
                        <div style="border: 1px solid #d8e1e8; background: #f4f7f9; border-radius: 8px; padding: 12px; max-width: 420px; margin-bottom: 8px;">
                            <strong><?= e($mediaLabel) ?></strong>
                            <?php if ($typedMediaSource !== ''): ?>
                                <div class="muted" style="overflow-wrap: anywhere;"><code><?= e($typedMediaSource) ?></code></div>
                            <?php endif; ?>
                            <?php if (isset($typedMediaPayload['duration'])): ?>
                                <div class="muted">duration <?= e((string) $typedMediaPayload['duration']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($typedMediaPayload['width']) || isset($typedMediaPayload['height'])): ?>
                                <div class="muted">
                                    <?= e((string) ($typedMediaPayload['width'] ?? '')) ?>x<?= e((string) ($typedMediaPayload['height'] ?? '')) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($typedMediaPayload['file_name']) && (string) $typedMediaPayload['file_name'] !== ''): ?>
                                <div class="muted"><?= e((string) $typedMediaPayload['file_name']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($typedMediaPayload['title']) && (string) $typedMediaPayload['title'] !== ''): ?>
                                <div class="muted"><?= e((string) $typedMediaPayload['title']) ?></div>
                            <?php endif; ?>
                            <?php if (isset($typedMediaPayload['performer']) && (string) $typedMediaPayload['performer'] !== ''): ?>
                                <div class="muted"><?= e((string) $typedMediaPayload['performer']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if ((string) $msg['text'] !== ''): ?>
                        <div style="white-space: pre-line;"><?php $renderMessageText((string) $msg['text']); ?></div>
                    <?php endif; ?>
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

    <?php if (!$chatFragment): ?>
        </div>
    <?php endif; ?>

    <?php if (!$chatFragment): ?>
        <div class="chat-compose<?= is_array($replyKeyboard) ? '' : ' chat-compose-single' ?>">
            <?php if (is_array($replyKeyboard)): ?>
                <div class="panel chat-input-tools">
                    <div class="chat-reply-keyboard">
                        <?php foreach ($replyKeyboard as $row): ?>
                            <?php if (!is_array($row)) { continue; } ?>
                            <div class="chat-reply-row">
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
            <form class="editor chat-message-form" method="post" action="/chat/send">
                <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                <label>
                    Сообщение
                    <textarea name="text" rows="3" required
                        placeholder="Введите сообщение..."
                    ></textarea>
                </label>
                <div class="actions">
                    <button type="submit">Отправить</button>
                </div>
            </form>
        </div>

        <?php if (($botCommands ?? []) !== []): ?>
            <details class="panel bot-command-picker">
                <summary>Команды бота</summary>
                <form method="post" action="/chat/send">
                    <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                    <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                    <select class="bot-command-select" name="text" required onchange="if (this.value !== '') { this.form.submit(); }">
                        <option value="" selected disabled>Выберите команду</option>
                        <?php foreach ($botCommands as $command): ?>
                            <option value="/<?= e($command['command']) ?>">
                                /<?= e($command['command']) ?> — <?= e($command['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </details>
        <?php endif; ?>

        <details class="panel chat-structured-inputs">
            <summary>Вложения</summary>
            <div class="chat-structured-grid">
                <form method="post" action="/chat/send" enctype="multipart/form-data">
                    <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                    <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                    <input type="hidden" name="message_type" value="photo">
                    <label>
                        Photo URL/file_id
                        <input type="text" name="photo" placeholder="https://example.test/photo.jpg">
                    </label>
                    <label>
                        Photo file
                        <input type="file" name="photo_file" accept="image/*">
                    </label>
                    <label>
                        Caption
                        <input type="text" name="caption">
                    </label>
                    <button type="submit" class="secondary">Photo</button>
                </form>
                <form method="post" action="/chat/send" enctype="multipart/form-data">
                    <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                    <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                    <input type="hidden" name="message_type" value="document">
                    <label>
                        Document URL/file_id
                        <input type="text" name="document" placeholder="https://example.test/file.pdf">
                    </label>
                    <label>
                        Document file
                        <input type="file" name="document_file">
                    </label>
                    <label>
                        Caption
                        <input type="text" name="caption">
                    </label>
                    <button type="submit" class="secondary">Document</button>
                </form>
                <form method="post" action="/chat/send">
                    <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                    <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                    <input type="hidden" name="message_type" value="location">
                    <label>
                        Latitude
                        <input type="text" name="latitude" placeholder="43.1155">
                    </label>
                    <label>
                        Longitude
                        <input type="text" name="longitude" placeholder="131.8855">
                    </label>
                    <button type="submit" class="secondary">Location</button>
                </form>
                <form method="post" action="/chat/send">
                    <input type="hidden" name="profile_id" value="<?= e($profile['id']) ?>">
                    <input type="hidden" name="bot_id" value="<?= e($bot['id']) ?>">
                    <input type="hidden" name="message_type" value="contact">
                    <label>
                        Phone
                        <input type="text" name="phone_number" placeholder="+70000000000">
                    </label>
                    <label>
                        First name
                        <input type="text" name="first_name">
                    </label>
                    <label>
                        Last name
                        <input type="text" name="last_name">
                    </label>
                    <button type="submit" class="secondary">Contact</button>
                </form>
            </div>
        </details>
    <?php endif; ?>

    <?php if (!$chatFragment && $latestUpdate !== null): ?>
        <section class="panel" style="margin-top: 18px;">
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

<?php $errors = $errors ?? []; ?>

<div class="toolbar">
    <h1><?= e($chat['title'] ?? 'Групповой чат') ?></h1>
    <a class="button secondary" href="/group-chats">Назад</a>
</div>

<div class="grid">
    <section class="panel">
        <h2>Чат</h2>
        <p><strong>Chat ID:</strong> <code><?= e($chat['chat_id']) ?></code></p>
        <p><strong>Type:</strong> <?= e($chat['type']) ?></p>
        <p><strong>Участники:</strong> <?= e($chat['member_count'] ?? count($members)) ?></p>
    </section>

    <form class="editor" method="post" action="/group-chats/<?= e($chat['chat_id']) ?>/members">
        <h2>Добавить участника</h2>
        <label>
            Пользователь
            <select name="profile_id" required>
                <option value="">Выберите profile</option>
                <?php foreach ($availableProfiles as $profile): ?>
                    <option value="<?= e($profile['id']) ?>">
                        <?= e($profile['first_name']) ?> <?= e($profile['last_name'] ?? '') ?>
                        (@<?= e($profile['username']) ?>, <?= e($profile['chat_type']) ?> #<?= e($profile['chat_id']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['profile_id'])): ?>
                <span class="field-error"><?= e($errors['profile_id']) ?></span>
            <?php endif; ?>
        </label>
        <button type="submit" <?= $availableProfiles === [] ? 'disabled' : '' ?>>Добавить</button>
    </form>
</div>

<?php if ($members === []): ?>
    <div class="empty">В чате пока нет участников.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Role</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($members as $member): ?>
            <tr>
                <td><?= e($member['id']) ?></td>
                <td>
                    <?= e($member['first_name']) ?> <?= e($member['last_name'] ?? '') ?><br>
                    <span class="muted">@<?= e($member['username']) ?> · <?= e($member['user_id']) ?></span>
                </td>
                <td><?= e($member['chat_role']) ?></td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/profiles/<?= e($member['id']) ?>/edit">Профиль</a>
                        <form method="post" action="/group-chats/<?= e($chat['chat_id']) ?>/members/<?= e($member['id']) ?>/delete" onsubmit="return confirm('Удалить участника из группы?');">
                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="toolbar">
    <h1>Профили</h1>
    <a class="button" href="/profiles/new">Новый профиль</a>
</div>

<?php if ($profiles === []): ?>
    <div class="empty">Профили еще не созданы.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Профиль</th>
            <th>Пользователь</th>
            <th>Чат</th>
            <th>Бот</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($profiles as $profile): ?>
            <tr>
                <td><?= e($profile['id']) ?></td>
                <td><strong><?= e($profile['name']) ?></strong></td>
                <td>
                    <?= e($profile['first_name']) ?> <?= e($profile['last_name'] ?? '') ?><br>
                    <span class="muted">@<?= e($profile['username']) ?> · <?= e($profile['user_id']) ?></span>
                </td>
                <td>
                    <?= e($profile['chat_type']) ?><br>
                    <span class="muted"><?= e($profile['chat_id']) ?></span>
                </td>
                <td>
                    <?php if ($profile['bot_display_name'] !== null): ?>
                        <?= e($profile['bot_display_name']) ?><br>
                        <span class="muted">@<?= e($profile['bot_username']) ?></span>
                    <?php else: ?>
                        <span class="muted">Не выбран</span>
                    <?php endif; ?>
                </td>
                <td><?= ((int) $profile['enabled']) === 1 ? 'Включен' : 'Выключен' ?></td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/profiles/<?= e($profile['id']) ?>/edit">Изменить</a>
                        <form method="post" action="/profiles/<?= e($profile['id']) ?>/delete" onsubmit="return confirm('Удалить профиль?');">
                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


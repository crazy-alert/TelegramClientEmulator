<div class="toolbar">
    <h1>Пользователи</h1>
    <a class="button" href="/profiles/new">Новый пользователь</a>
</div>

<?php if ($users === []): ?>
    <div class="empty">Пользователи еще не созданы.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Чат</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $profile): ?>
            <tr>
                <td><?= e($profile['id']) ?></td>
                <td>
                    <?= e($profile['first_name']) ?> <?= e($profile['last_name'] ?? '') ?><br>
                    <span class="muted">@<?= e($profile['username']) ?> · <?= e($profile['user_id']) ?></span>
                </td>
                <td>
                    <?= e($profile['chat_type']) ?><br>
                    <span class="muted"><?= e($profile['chat_id']) ?></span>
                </td>
                <td><?= ((int) $profile['enabled']) === 1 ? 'Включен' : 'Выключен' ?></td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/profiles/<?= e($profile['id']) ?>/edit">Изменить</a>
                        <form method="post" action="/profiles/<?= e($profile['id']) ?>/delete" onsubmit="return confirm('Удалить пользователя?');">
                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

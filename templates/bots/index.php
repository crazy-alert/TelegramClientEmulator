<div class="toolbar">
    <h1>Боты</h1>
    <a class="button" href="/bots/new">Новый бот</a>
</div>

<?php if ($bots === []): ?>
    <div class="empty">Боты еще не созданы.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>Название</th>
            <th>Token</th>
            <th>Режим</th>
            <th>Webhook</th>
            <th>Статус</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($bots as $bot): ?>
            <tr>
                <td><?= e($bot['id']) ?></td>
                <td>
                    <strong><?= e($bot['display_name']) ?></strong><br>
                    <span class="muted">@<?= e($bot['username']) ?></span>
                </td>
                <td><code><?= e($bot['token']) ?></code></td>
                <td><?= e($bot['delivery_mode']) ?></td>
                <td><code><?= e($bot['webhook_url'] ?? '') ?></code></td>
                <td><?= ((int) $bot['enabled']) === 1 ? 'Включен' : 'Выключен' ?></td>
                <td>
                    <div class="actions">
                        <a class="button secondary" href="/bots/<?= e($bot['id']) ?>/edit">Изменить</a>
                        <form method="post" action="/bots/<?= e($bot['id']) ?>/delete" onsubmit="return confirm('Удалить бота?');">
                            <button class="danger" type="submit">Удалить</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>


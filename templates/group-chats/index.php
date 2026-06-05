<div class="toolbar">
    <h1>Групповые чаты</h1>
    <a class="button secondary" href="/profiles/new">Новый пользователь</a>
</div>

<?php if ($chats === []): ?>
    <div class="empty">Group/supergroup чаты еще не созданы. Создайте пользователя с типом чата group или supergroup.</div>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th>Title</th>
            <th>Chat ID</th>
            <th>Type</th>
            <th>Участники</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($chats as $chat): ?>
            <tr>
                <td><?= e($chat['title'] ?? '') ?></td>
                <td><code><?= e($chat['chat_id']) ?></code></td>
                <td><?= e($chat['type']) ?></td>
                <td><?= e($chat['member_count'] ?? 0) ?></td>
                <td>
                    <a class="button secondary" href="/group-chats/<?= e($chat['chat_id']) ?>">Участники</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

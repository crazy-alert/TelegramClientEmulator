<?php
$isEdit = $profile !== null;
$action = $isEdit ? '/profiles/' . $profile['id'] : '/profiles';
?>

<div class="toolbar">
    <h1><?= $isEdit ? 'Редактирование профиля' : 'Новый профиль' ?></h1>
    <a class="button secondary" href="/profiles">Назад</a>
</div>

<form class="editor" method="post" action="<?= e($action) ?>">
    <label>
        Название профиля
        <input name="name" required value="<?= e($profile['name'] ?? '') ?>" placeholder="Пользователь 1">
    </label>

    <label>
        Активный бот
        <select name="active_bot_id">
            <option value="">Не выбран</option>
            <?php foreach ($bots as $bot): ?>
                <option value="<?= e($bot['id']) ?>" <?= (string) ($profile['active_bot_id'] ?? '') === (string) $bot['id'] ? 'selected' : '' ?>>
                    <?= e($bot['display_name']) ?> (@<?= e($bot['username']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label>
        User ID
        <input name="user_id" required inputmode="numeric" value="<?= e($profile['user_id'] ?? '') ?>" placeholder="1001">
    </label>

    <label>
        Username
        <input name="username" required value="<?= e($profile['username'] ?? '') ?>" placeholder="dev_user">
    </label>

    <label>
        Имя
        <input name="first_name" required value="<?= e($profile['first_name'] ?? '') ?>" placeholder="Dev">
    </label>

    <label>
        Фамилия
        <input name="last_name" value="<?= e($profile['last_name'] ?? '') ?>" placeholder="User">
    </label>

    <label>
        Chat ID
        <input name="chat_id" required inputmode="numeric" value="<?= e($profile['chat_id'] ?? '') ?>" placeholder="1001">
    </label>

    <label>
        Тип чата
        <?php $chatType = $profile['chat_type'] ?? 'private'; ?>
        <select name="chat_type">
            <option value="private" <?= $chatType === 'private' ? 'selected' : '' ?>>private</option>
            <option value="group" <?= $chatType === 'group' ? 'selected' : '' ?>>group</option>
            <option value="supergroup" <?= $chatType === 'supergroup' ? 'selected' : '' ?>>supergroup</option>
            <option value="channel" <?= $chatType === 'channel' ? 'selected' : '' ?>>channel</option>
        </select>
    </label>

    <label>
        Язык
        <input name="language_code" value="<?= e($profile['language_code'] ?? 'ru') ?>">
    </label>

    <label class="checkbox">
        <input type="checkbox" name="enabled" value="1" <?= ((int) ($profile['enabled'] ?? 1)) === 1 ? 'checked' : '' ?>>
        Включен
    </label>

    <div class="actions">
        <button type="submit">Сохранить</button>
        <a class="button secondary" href="/profiles">Отмена</a>
    </div>
</form>


<?php
$isEdit = $profile !== null;
$action = $isEdit ? '/profiles/' . $profile['id'] : '/profiles';
$errors = $errors ?? [];
?>

<div class="toolbar">
    <h1><?= $isEdit ? 'Редактирование пользователя' : 'Новый пользователь' ?></h1>
    <a class="button secondary" href="/profiles">Назад</a>
</div>

<form class="editor" method="post" action="<?= e($action) ?>">
    <label>
        User ID
        <input name="user_id" required inputmode="numeric" value="<?= e($profile['user_id'] ?? '') ?>" placeholder="1001">
        <?php if (isset($errors['user_id'])): ?>
            <span class="field-error"><?= e($errors['user_id']) ?></span>
        <?php endif; ?>
    </label>

    <label>
        Username
        <input name="username" required value="<?= e($profile['username'] ?? '') ?>" placeholder="dev_user">
        <?php if (isset($errors['username'])): ?>
            <span class="field-error"><?= e($errors['username']) ?></span>
        <?php endif; ?>
    </label>

    <label>
        Имя
        <input name="first_name" required value="<?= e($profile['first_name'] ?? '') ?>" placeholder="Dev">
        <?php if (isset($errors['first_name'])): ?>
            <span class="field-error"><?= e($errors['first_name']) ?></span>
        <?php endif; ?>
    </label>

    <label>
        Фамилия
        <input name="last_name" value="<?= e($profile['last_name'] ?? '') ?>" placeholder="User">
    </label>

    <label>
        Chat ID
        <input name="chat_id" required inputmode="numeric" value="<?= e($profile['chat_id'] ?? '') ?>" placeholder="1001">
        <?php if (isset($errors['chat_id'])): ?>
            <span class="field-error"><?= e($errors['chat_id']) ?></span>
        <?php endif; ?>
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
        <?php if (isset($errors['chat_type'])): ?>
            <span class="field-error"><?= e($errors['chat_type']) ?></span>
        <?php endif; ?>
    </label>

    <label>
        Язык
        <input name="language_code" value="<?= e($profile['language_code'] ?? 'ru') ?>">
        <?php if (isset($errors['language_code'])): ?>
            <span class="field-error"><?= e($errors['language_code']) ?></span>
        <?php endif; ?>
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

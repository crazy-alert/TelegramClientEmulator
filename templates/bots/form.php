<?php
$isEdit = $bot !== null;
$action = $isEdit ? '/bots/' . $bot['id'] : '/bots';
$tokenPlaceholder = $bot['token'] ?? $generatedCredentials['token'] ?? '100001:local-dev-token-000000';
$botIdPlaceholder = $bot['bot_id'] ?? $generatedCredentials['bot_id'] ?? '100001';
?>

<div class="toolbar">
    <h1><?= $isEdit ? 'Редактирование бота' : 'Новый бот' ?></h1>
    <a class="button secondary" href="/bots">Назад</a>
</div>

<form class="editor" method="post" action="<?= e($action) ?>">
    <?php if (!$isEdit && $generatedCredentials !== null): ?>
        <input type="hidden" name="generated_token" value="<?= e($generatedCredentials['token']) ?>">
    <?php endif; ?>

    <label>
        Название
        <input name="display_name" required value="<?= e($bot['display_name'] ?? '') ?>" placeholder="Локальный тестовый бот">
    </label>

    <label>
        Token
        <input name="token" value="<?= e($bot['token'] ?? '') ?>" placeholder="<?= e((string) $tokenPlaceholder) ?>">
    </label>

    <label>
        Bot ID
        <input name="bot_id" inputmode="numeric" value="<?= e($bot['bot_id'] ?? '') ?>" placeholder="<?= e((string) $botIdPlaceholder) ?>">
    </label>

    <label>
        Username
        <input name="username" required value="<?= e($bot['username'] ?? '') ?>" placeholder="local_test_bot">
    </label>

    <label>
        Режим доставки
        <select name="delivery_mode">
            <?php $deliveryMode = $bot['delivery_mode'] ?? 'long_polling'; ?>
            <option value="long_polling" <?= $deliveryMode === 'long_polling' ? 'selected' : '' ?>>Long Polling</option>
            <option value="webhook" <?= $deliveryMode === 'webhook' ? 'selected' : '' ?>>Webhook</option>
        </select>
    </label>

    <label>
        Webhook URL
        <input name="webhook_url" value="<?= e($bot['webhook_url'] ?? '') ?>" placeholder="http://bot:3000/telegram/webhook">
    </label>

    <label>
        Webhook secret token
        <input name="webhook_secret_token" value="<?= e($bot['webhook_secret_token'] ?? '') ?>">
    </label>

    <label class="checkbox">
        <input type="checkbox" name="enabled" value="1" <?= ((int) ($bot['enabled'] ?? 1)) === 1 ? 'checked' : '' ?>>
        Включен
    </label>

    <div class="actions">
        <button type="submit">Сохранить</button>
        <a class="button secondary" href="/bots">Отмена</a>
    </div>
</form>

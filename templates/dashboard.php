<h1>Панель</h1>

<div class="grid">
    <section class="panel">
        <div class="toolbar">
            <h2>Боты</h2>
            <a class="button" href="/bots/new">Добавить</a>
        </div>

        <p class="muted">Настроено ботов: <?= e(count($bots)) ?></p>
        <p><a href="/bots">Открыть управление ботами</a></p>
    </section>

    <section class="panel">
        <div class="toolbar">
            <h2>Пользователи</h2>
            <a class="button" href="/profiles/new">Добавить</a>
        </div>

        <p class="muted">Настроено пользователей: <?= e(count($users)) ?></p>
        <p><a href="/profiles">Открыть управление пользователями</a></p>
    </section>
</div>

<section class="panel" style="margin-top: 18px;">
    <h2>Webhook delivery</h2>
    <p class="muted">
        Текущий timeout: <strong><?= e($webhookTimeoutMs) ?> мс</strong>.
        Значение по умолчанию из окружения: <?= e($webhookTimeoutDefaultMs) ?> мс.
    </p>
    <form method="post" action="/settings/webhook-timeout" class="form-inline">
        <label>
            Timeout, мс
            <input
                type="number"
                name="webhook_timeout_ms"
                value="<?= e($webhookTimeoutMs) ?>"
                min="<?= e($webhookTimeoutMinMs) ?>"
                max="<?= e($webhookTimeoutMaxMs) ?>"
                step="100"
                required
            >
        </label>
        <button type="submit">Сохранить</button>
    </form>
    <p class="muted">Допустимый диапазон: <?= e($webhookTimeoutMinMs) ?>–<?= e($webhookTimeoutMaxMs) ?> мс.</p>

    <hr>
    <p class="muted">
        Development retry: <strong><?= e($webhookRetryMaxAttempts) ?></strong> попыток,
        delay <strong><?= e($webhookRetryDelayMs) ?> мс</strong>.
        По умолчанию из окружения: <?= e($webhookRetryMaxAttemptsDefault) ?> попыток,
        <?= e($webhookRetryDelayDefaultMs) ?> мс.
    </p>
    <form method="post" action="/settings/webhook-retry" class="form-inline">
        <label>
            Max attempts
            <input
                type="number"
                name="webhook_retry_max_attempts"
                value="<?= e($webhookRetryMaxAttempts) ?>"
                min="<?= e($webhookRetryMaxAttemptsMin) ?>"
                max="<?= e($webhookRetryMaxAttemptsMax) ?>"
                required
            >
        </label>
        <label>
            Retry delay, мс
            <input
                type="number"
                name="webhook_retry_delay_ms"
                value="<?= e($webhookRetryDelayMs) ?>"
                min="<?= e($webhookRetryDelayMinMs) ?>"
                max="<?= e($webhookRetryDelayMaxMs) ?>"
                step="100"
                required
            >
        </label>
        <button type="submit">Сохранить retry</button>
    </form>
    <p class="muted">
        Это короткий синхронный helper для локальной разработки, а не production scheduler.
        Ручной retry failed updates остается доступен на экране `/updates`.
    </p>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2>Обновления TelegramEmulator</h2>
    <p>
        Приложение не запускает Git и не выполняет команды из PHP. Проверка только сравнивает локальный
        <code>version.json</code> с удаленным <code>version.json</code> проекта.
    </p>
    <form method="post" action="/telegram-emulator-updates/check" class="form-inline">
        <button type="submit">Проверить обновления TelegramEmulator</button>
    </form>

    <?php if (isset($updateCheck) && is_array($updateCheck)): ?>
        <hr>
        <?php if (($updateCheck['ok'] ?? false) === true): ?>
            <dl>
                <dt>Локальный commit</dt>
                <dd><code><?= e($updateCheck['current_commit'] ?? '') ?></code></dd>
                <dt>Последний commit</dt>
                <dd>
                    <code><?= e($updateCheck['latest_commit'] ?? '') ?></code>
                    <?php if (!empty($updateCheck['latest_url'])): ?>
                        <br><a href="<?= e($updateCheck['latest_url']) ?>">Открыть commit</a>
                    <?php endif; ?>
                </dd>
            </dl>

            <?php if (($updateCheck['update_available'] ?? false) === true): ?>
                <p><strong>Есть обновление.</strong></p>
                <p>Обновите проект вручную из папки, где лежит <code>docker-compose.yml</code>:</p>
                <pre><code>cd S:\TelegramClientEmulator
git pull
docker compose pull
docker compose up -d</code></pre>
                <p class="muted">
                    Если вы запускали эмулятор на другом порту, оставьте прежний <code>HOST_PORT</code>
                    или <code>.env</code> без изменений. Runtime-данные в <code>data/</code> не входят
                    в git и не должны перезаписываться обычным <code>git pull</code>.
                </p>
            <?php else: ?>
                <p><strong>Обновления не найдены.</strong> Локальный commit совпадает с последним commit ветки.</p>
            <?php endif; ?>
        <?php else: ?>
            <p><strong>Проверка не выполнена.</strong></p>
            <p class="muted"><?= e($updateCheck['error'] ?? 'Неизвестная ошибка проверки обновлений.') ?></p>
            <?php if (!empty($updateCheck['source_url'])): ?>
                <p class="muted">Источник проверки: <code><?= e($updateCheck['source_url']) ?></code></p>
            <?php endif; ?>
            <p>Проверить и обновить проект можно вручную:</p>
            <pre><code>cd S:\TelegramClientEmulator
git pull
docker compose up -d</code></pre>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">
            Источник по умолчанию: <code>version.json</code> из ветки <code>master</code>
            в GitHub-репозитории проекта.
        </p>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2>Состояние проекта</h2>
    <p>
        Основной локальный workflow готов: боты и пользователи хранятся в SQLite, чат открывается
        для выбранной пары пользователь-бот, updates доставляются через webhook или Long Polling.
    </p>
    <p class="muted">База данных: <code><?= e($databasePath) ?></code></p>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2>Health</h2>
    <p>
        Сервис отвечает, хранилище подключено через SQLite.
    </p>
    <p class="muted">
        Raw health endpoint остается доступен напрямую:
        <a href="/health"><code>/health</code></a>.
    </p>
    <dl>
        <dt>service</dt>
        <dd><code>telegram-emulator</code></dd>
        <dt>storage.driver</dt>
        <dd><code>sqlite</code></dd>
        <dt>storage.path</dt>
        <dd><code><?= e($databasePath) ?></code></dd>
    </dl>
</section>

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
    <h2>Состояние Этапа 1</h2>
    <p>
        CRUD для ботов и пользователей работает через SQLite. Чат открывается для выбранной пары пользователь-бот.
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

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
            <h2>Профили</h2>
            <a class="button" href="/profiles/new">Добавить</a>
        </div>

        <p class="muted">Настроено профилей: <?= e(count($profiles)) ?></p>
        <p><a href="/profiles">Открыть управление профилями</a></p>
    </section>
</div>

<section class="panel" style="margin-top: 18px;">
    <h2>Состояние Этапа 1</h2>
    <p>
        CRUD для ботов и профилей работает через SQLite. Следующий шаг: базовый чат,
        генерация Telegram-like updates и очередь Long Polling.
    </p>
    <p class="muted">База данных: <code><?= e($databasePath) ?></code></p>
</section>


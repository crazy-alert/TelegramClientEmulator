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
    <h2>Состояние Этапа 1</h2>
    <p>
        CRUD для ботов и пользователей работает через SQLite. Чат открывается для выбранной пары пользователь-бот.
    </p>
    <p class="muted">База данных: <code><?= e($databasePath) ?></code></p>
</section>

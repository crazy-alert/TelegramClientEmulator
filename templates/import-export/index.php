<h1>Import/export</h1>

<div class="grid">
    <section class="panel">
        <h2>Экспорт</h2>
        <p class="muted">
            Экспортируются только настройки ботов и пользователей. История сообщений, updates и delivery attempts не входят в JSON.
        </p>
        <div class="actions">
            <a class="button secondary" href="/export/bots">Скачать bots JSON</a>
            <a class="button secondary" href="/export/profiles">Скачать profiles JSON</a>
            <a class="button secondary" href="/export/fixture-pack">Скачать fixture pack JSON</a>
        </div>
    </section>

    <section class="panel">
        <h2>Ограничения импорта</h2>
        <p class="muted">
            Импорт отклоняется при конфликте `token` для ботов, а также `user_id` или `chat_id` для пользователей.
            История и runtime-данные не импортируются.
        </p>
    </section>
</div>

<div class="grid" style="margin-top: 18px;">
    <form class="editor" method="post" action="/import/bots">
        <h2>Импорт ботов</h2>
        <label>
            JSON
            <textarea
                name="payload"
                rows="12"
                required
                placeholder='{"bots":[{"token":"123456:local-dev-token","bot_id":123456,"username":"local_bot","display_name":"Local Bot","delivery_mode":"long_polling","webhook_url":null,"webhook_secret_token":null,"enabled":true}]}'
                style="width: 100%; resize: vertical; font: inherit; padding: 8px 10px; border: 1px solid #c8d3dc; border-radius: 6px;"
            ></textarea>
        </label>
        <div class="actions">
            <button type="submit">Импортировать ботов</button>
        </div>
    </form>

    <form class="editor" method="post" action="/import/profiles">
        <h2>Импорт пользователей</h2>
        <label>
            JSON
            <textarea
                name="payload"
                rows="12"
                required
                placeholder='{"profiles":[{"user_id":1001,"username":"dev_user","first_name":"Dev","last_name":"User","chat_id":1001,"chat_type":"private","language_code":"ru","enabled":true}]}'
                style="width: 100%; resize: vertical; font: inherit; padding: 8px 10px; border: 1px solid #c8d3dc; border-radius: 6px;"
            ></textarea>
        </label>
        <div class="actions">
            <button type="submit">Импортировать пользователей</button>
        </div>
    </form>

    <form class="editor" method="post" action="/import/fixture-pack">
        <h2>Импорт fixture pack</h2>
        <label>
            JSON
            <textarea
                name="payload"
                rows="12"
                required
                placeholder='{"kind":"telegram-emulator-fixture-pack","version":2,"bots":[],"profiles":[],"chats":[],"bot_commands":[],"media_manifest":{"included":false}}'
                style="width: 100%; resize: vertical; font: inherit; padding: 8px 10px; border: 1px solid #c8d3dc; border-radius: 6px;"
            ></textarea>
        </label>
        <p class="muted">Fixture pack импортирует настройки ботов, пользователей, chats и bot_commands. Бинарные media-файлы в JSON не входят.</p>
        <div class="actions">
            <button type="submit">Импортировать fixture pack</button>
        </div>
    </form>
</div>

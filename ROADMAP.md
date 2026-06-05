# Roadmap

## 1. Архитектурная позиция

Проект должен быть локальным Telegram Bot API emulator, а не оберткой вокруг одного конкретного бота.

Базовая модель:

- в эмуляторе может быть несколько ботов;
- у каждого бота есть свой token, bot id, username;
- пользователь описывает Telegram-like пользователя и чат;
- конкретный диалог открывается для выбранной пары пользователь-бот;
- история сообщений привязана к паре пользователь-бот;
- бот может получать updates через webhook или Long Polling;
- бот отправляет ответы через локальные routes вида `/bot{token}/sendMessage`.

Главный сценарий: разработчик запускает эмулятор и один или несколько контейнеров ботов в Docker Compose, открывает веб-интерфейс, выбирает пользователя и бота, отправляет сообщение, а бот получает update так же, как получил бы его от Telegram.

## 2. Выбранный стек

- PHP 8.3+ как основной язык backend.
- HTMX для интерактивности без тяжелого SPA.
- SQLite для локального хранения состояния.
- Docker Compose как основной способ запуска.
- Серверный HTML rendering, минимальный JavaScript только там, где HTMX недостаточно.

Причина выбора: проект является developer tool с формами, таблицами, чатовым интерфейсом и request/response инспектором. Для такого продукта PHP + HTMX дают простую модель разработки, быстрый старт контейнера и меньше инфраструктуры, чем SPA.

## 3. Ключевые доменные сущности

### Bot

Бот описывает локально эмулируемого Telegram-бота.

Поля:

- `id`
- `token`
- `bot_id`
- `username`
- `display_name`
- `delivery_mode`: `webhook` или `long_polling`
- `webhook_url`
- `webhook_secret_token`
- `enabled`
- `created_at`
- `updated_at`

### User

Пользователь описывает тестового Telegram-пользователя и чат.

Поля:

- `id`
- `name`
- `user_id`
- `username`
- `first_name`
- `last_name`
- `chat_id`
- `chat_type`
- `language_code`
- `enabled`
- `created_at`
- `updated_at`

### Message

Сообщение хранит видимую историю диалога.

Поля:

- `id`
- `bot_id`
- `profile_id`
- `chat_id`
- `telegram_message_id`
- `direction`: `user` или `bot`
- `text`
- `raw_payload`
- `created_at`

### Update

Update хранит Telegram-like событие, созданное эмулятором.

Поля:

- `id`
- `bot_id`
- `profile_id`
- `update_id`
- `payload`
- `delivery_mode`
- `queue_state`: `pending`, `delivered`, `confirmed`, `failed`
- `created_at`
- `delivered_at`
- `confirmed_at`

### DeliveryAttempt

Попытка webhook-доставки.

Поля:

- `id`
- `update_id`
- `bot_id`
- `webhook_url`
- `request_headers`
- `request_body`
- `response_status`
- `response_headers`
- `response_body`
- `duration_ms`
- `error`
- `created_at`

## 4. Bot API surface

### Реализованные методы

- `GET|POST /bot{token}/getMe`
- `GET|POST /bot{token}/getUpdates`
- `GET|POST /bot{token}/getFile`
- `POST /bot{token}/sendMessage`
- `POST /bot{token}/sendPhoto`
- `POST /bot{token}/sendDocument`
- `POST /bot{token}/sendVideo`
- `POST /bot{token}/sendAnimation`
- `POST /bot{token}/sendAudio`
- `POST /bot{token}/sendVoice`
- `POST /bot{token}/sendVideoNote`
- `POST /bot{token}/sendSticker`
- `POST /bot{token}/sendPoll`
- `POST /bot{token}/sendLocation`
- `POST /bot{token}/sendVenue`
- `POST /bot{token}/sendContact`
- `POST /bot{token}/sendDice`
- `POST /bot{token}/setWebhook`
- `POST /bot{token}/deleteWebhook`
- `GET|POST /bot{token}/getWebhookInfo`
- `POST /bot{token}/setMyCommands`
- `GET|POST /bot{token}/getMyCommands`
- `POST /bot{token}/deleteMyCommands`
- `POST /bot{token}/answerCallbackQuery`

### Следующие методы

На ближайшую задачу вынесен refactor renderer.

Неподдерживаемые методы должны возвращать Telegram-like ошибку:

```json
{
  "ok": false,
  "error_code": 501,
  "description": "Метод пока не поддерживается эмулятором"
}
```

## 5. Этапы реализации

### Этап 0: фундамент проекта

Цель: получить запускаемый PHP-проект в Docker.

Статус: реализовано.

Результат:

- `docker-compose.yml` на готовом образе `php:8.3-cli-alpine`;
- встроенный PHP HTTP server;
- endpoint `GET /health`;
- базовая структура `public/`, `src/`, `templates/`, `var/`;
- SQLite подключение;
- миграции;
- bind mount проекта в `/app`;
- подключение к внешней Docker-сети `app-backend`;
- публикация порта через `HOST_PORT`, по умолчанию `8080`;
- `php.ini` с отключенным автоматическим чтением POST body, чтобы вручную и стабильно парсить JSON/form-urlencoded запросы.

Критерий готовности: `docker compose up -d` поднимает приложение, контейнер становится healthy, а `GET /health` возвращает успешный JSON.

### Этап 1: модель ботов и пользователей

Цель: убрать привязку к одному token и заложить мультиботовую модель.

Статус: реализовано.

Результат:

- CRUD ботов;
- CRUD пользователей;
- выбор пользователя и бота на экране чата через URL-параметры, без cookie-состояния;
- автоматическая генерация `bot_id` и `token` при создании бота;
- `bot_id` извлекается из token, если token соответствует Telegram-like формату;
- базовая нормализация token, user id, chat id и username;
- хранение настроек в SQLite.

Критерий готовности: в UI можно создать два бота, двух пользователей и открыть разные пары пользователь-бот в разных вкладках без конфликта состояния.

### Этап 2: базовый чат и генерация updates

Цель: создать первый локальный Telegram-like message loop внутри UI.

Статус: реализовано.

Результат:

- экран чата;
- отправка текстового сообщения от выбранного пользователя;
- генерация `Update`;
- генерация `message_id` и `update_id`;
- сохранение сообщения и raw payload;
- inspector для последнего update.

Критерий готовности: сообщение из UI сохраняется в истории, а raw `Update` соответствует Telegram Bot API shape.

### Этап 3: Long Polling

Цель: поддержать ботов, которые работают через `getUpdates`.

Статус: реализовано.

Результат:

- очередь updates по каждому боту;
- endpoint `GET|POST /bot{token}/getUpdates`;
- поддержка `offset`, `limit`, `timeout`, `allowed_updates`;
- подтверждение updates через offset;
- отображение состояния очереди в UI.

Принятые ограничения:

- `timeout` в MVP ограничен коротким ожиданием до 3 секунд, чтобы не блокировать single-process встроенный PHP server надолго.

Критерий готовности: тестовый бот может получать сообщения из эмулятора через Long Polling и не получает подтвержденные updates повторно.

### Этап 4: webhook-доставка

Цель: поддержать ботов, которые работают через webhook.

Статус: реализовано в базовом виде.

Реализовано:

- `setWebhook`;
- `deleteWebhook`;
- `getWebhookInfo`;
- хранение webhook URL и secret token;
- переключение режима доставки между `webhook` и `long_polling`;
- 409 conflict для `getUpdates`, если активен webhook.
- отправка update на webhook URL выбранного бота;
- сохранение request/response;
- отображение ошибок доставки.
- ручной resend failed delivery из inspector последнего update.
- отдельный список delivery attempts и фильтры по боту/update.
- настройки webhook timeout в UI с диапазоном 1000–60000 мс.

Критерий готовности: бот-контейнер получает update через Docker service URL, а эмулятор показывает статус доставки.

### Этап 5: ответы бота

Цель: показывать ответы бота в Telegram-like интерфейсе.

Статус: реализовано для текстовых сообщений.

Результат:

- `sendMessage`;
- сохранение bot messages;
- отображение ответов в чате;
- Telegram-like response body;
- поддержка JSON и form-encoded request body.
- поддержка `reply_markup` для `inline_keyboard` и `keyboard`;
- отображение inline/reply-кнопок в чате;
- клики по reply-кнопкам создают обычные message updates;
- клики по inline-кнопкам с `callback_data` создают `callback_query` updates;
- сохранение и показ команд бота через `setMyCommands`/`getMyCommands`/`deleteMyCommands`;
- компактный select команд рядом с полем ввода и кликабельные команды в истории сообщений.

Принятые ограничения:

- реализованы только текстовые сообщения;
- attachments и остальные параметры `sendMessage` будут добавляться по мере появления сценариев.
- command scopes и language-specific команды пока не разделяются.
- URL inline-кнопки открываются ссылкой в UI, но не создают update.

Критерий готовности: бот получает сообщение от пользователя и через `/sendMessage` добавляет ответ в видимый чат.

### Этап 6: HTMX-интерактивность и ergonomics

Цель: сделать интерфейс удобным для ежедневной разработки.

Статус: частично реализовано.

Результат:

- HTMX polling для обновления чата — реализовано через `/chat/fragment`;
- inline validation форм ботов и пользователей — реализовано для основных ошибок;
- вкладки: чат, боты, пользователи, updates, delivery attempts — updates и delivery attempts реализованы;
- inspector request/response — реализован для Bot API HTTP-логов и webhook delivery attempts;
- import/export ботов и пользователей — реализовано для JSON без истории сообщений; fixture pack v2 добавлен для повторяемых тестовых сценариев с `bot_commands`, `chats` и `media_manifest`;
- очистка истории по пользователю или боту — реализована для диалога и pending/confirmed updates выбранного бота.

Критерий готовности: основной workflow выполняется из браузера без ручного редактирования файлов и без перезагрузки страницы для частых действий.

### Этап 7: расширение Telegram compatibility

Цель: покрыть частые возможности ботов.

Статус: частично реализовано.

Результат:

- inline keyboard rendering — реализовано для `callback_data` и `url`;
- callback query generation — реализовано для inline-кнопок с `callback_data`;
- `answerCallbackQuery` — реализовано в минимальном виде;
- reply keyboard — реализовано для отправки текстовых кнопок;
- `editMessageText` — реализовано для текстовых сообщений бота и optional `reply_markup`;
- базовый `sendPhoto` — реализовано для строкового/URL `photo`, multipart upload, caption и optional `reply_markup`;
- базовый `sendDocument` — реализовано для строкового/URL `document`, multipart upload, caption и optional `reply_markup`;
- `getFile` и локальная отдача media — реализованы для файлов, сохраненных в `MEDIA_DIR`;
- базовые audio/video media методы — реализованы `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote` и `sendSticker` для строкового/URL media и multipart upload;
- poll/quiz сообщения — реализован базовый `sendPoll` как read-only `Message.poll`;
- structured messages — реализованы `sendLocation`, `sendVenue`, `sendContact` и `sendDice` без файлового upload.
- прием structured сообщений от пользователя через UI — реализован для photo/document по URL, file_id или локальному файлу, location и contact.

Критерий готовности: можно локально тестировать ботов с кнопками и редактированием сообщений.

### Этап 8: качество и документация

Цель: подготовить проект к использованию не только автором.

Статус: начато.

Результат:

- тесты доменной логики — начато через `tests/bot_api_test.php`;
- тесты Bot API routes — начато через `tests/bot_api_test.php`;
- тесты Long Polling offset behavior — реализованы в интеграционном тесте;
- документация запуска тестов — добавлена в README;
- документация Docker Compose сценариев — добавлена в README;
- примеры интеграции для PHP, Python и Node.js bot frameworks — добавлены в `docs/framework-examples.md`;
- описание ограничений эмулятора — добавлено в `docs/limitations.md`.

Критерий готовности: новый разработчик может поднять проект и подключить своего бота по README без устных пояснений.

## 6. Приоритеты MVP

Статус по ключевым приоритетам:

1. Мультиботовая модель — реализовано.
2. Пользователи и чаты — реализовано.
3. Генерация корректных text message updates — реализовано.
4. Long Polling через `getUpdates` — реализовано.
5. `sendMessage` для отображения ответов — реализовано для текста.
6. Webhook configuration (`setWebhook`, `deleteWebhook`, `getWebhookInfo`) — реализовано.
7. Webhook delivery — реализовано в базовом виде.
8. Инспектор payload и ошибок — payload inspector и последний delivery attempt реализованы частично; отдельный delivery inspector впереди.
9. Команды бота и кнопки — реализованы в базовом виде для default-команд, inline keyboard, reply keyboard и callback query.

Long Polling был реализован до webhook-доставки, потому что он не требует отдельного HTTP endpoint в контейнере бота и быстрее проверяет корректность очереди updates.

## 7. Архитектурные ограничения

- Не использовать один глобальный `TELEGRAM_BOT_TOKEN` как конфигурацию проекта.
- Token является настройкой конкретного бота внутри эмулятора.
- Не хранить реальные production token в примерах.
- Не подключаться к настоящему Telegram.
- Не строить SPA без необходимости; базовый UI должен работать через server-rendered HTML и HTMX.
- Не пытаться реализовать весь Telegram Bot API до появления реальных сценариев.

## 8. Открытые решения

- Формат import/export принят: JSON envelope v1 для bots/profiles без истории; fixture pack v2 использует top-level массивы `bots`, `profiles`, `chats`, `bot_commands` и `media_manifest`. Архив нужен только при появлении экспорта бинарных media.
- Групповые чаты начаты: несколько сохраненных пользователей могут иметь один group/supergroup `chat_id`, `/chat` выбирает отправителя, update содержит `message.chat.type=group`, история группы читается по `bot_id + chat_id`. Добавлены нормализованные `chats` и `chat_members`, которые синхронизируются из profiles; отдельный UI для membership/title/roles пока отложен.
- Режим "один пользователь общается с несколькими ботами в одном экране" не входит в текущий scope; альтернатива — открыть одну пару `profile_id`/`bot_id` на вкладку.
- Micro-framework не внедряется сейчас: решение зафиксировано в `docs/adr-routing.md`, текущий custom router остается до явной необходимости.
- Стратегия тестов принята: текущий самописный Docker HTTP smoke runner остается основным контуром, PHPUnit не добавляется без явной необходимости; решение зафиксировано в `docs/adr-testing.md`.

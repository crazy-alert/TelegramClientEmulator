# Техническое задание: Telegram Bot Emulator

## 1. Цель

Создать Dockerized локальный инструмент разработки для Telegram-ботов.

Инструмент должен предоставлять минимальный Telegram-like веб-интерфейс и локальный Bot API эмулятор с поддержкой webhook и Long Polling, чтобы разработчик мог тестировать поведение нескольких ботов без настоящих Telegram-клиентов, публичных webhook, ngrok и production-состояния бота.

## 2. Целевой пользователь

Основной пользователь: backend-разработчик, который делает Telegram-ботов.

Типичный workflow:

1. Запустить контейнер бота и контейнер эмулятора через Docker Compose.
2. Открыть эмулятор в браузере.
3. Выбрать или создать тестового пользователя.
4. Выбрать пользователя и бота на экране чата, а для бота настроить режим доставки: webhook или Long Polling.
5. Отправлять сообщения в веб-интерфейсе.
6. Смотреть ответы бота и инспектировать raw request/response payload.

## 3. Не входит в scope

Первые версии не должны:

- реализовывать полный Telegram client UI;
- реализовывать полный Telegram Bot API;
- подключаться к настоящему Telegram;
- заменять end-to-end тесты против настоящей Telegram-платформы;
- поддерживать production traffic.

## 4. Продуктовые требования

### 4.1 Боты и пользователи

Проект не должен быть привязан к одному bot token. В системе должны быть отдельные сущности бота и пользователя.

Бот описывает приложение, которое получает updates и вызывает Bot API mock.

Обязательные поля бота:

- `id`: внутренний id бота.
- `token`: фейковый или похожий на настоящий token Telegram-бота.
- `botId`: numeric bot id, полученный из token, если возможно, или заданный вручную.
- `username`: username бота без `@`.
- `displayName`: отображаемое имя бота.
- `deliveryMode`: `webhook` или `long_polling`.
- `webhookUrl`: URL, который принимает сгенерированные updates в режиме webhook.
- `webhookSecretToken`: необязательное значение, отправляемое как `X-Telegram-Bot-Api-Secret-Token`.
- `enabled`: может ли бот получать updates.
- `createdAt`, `updatedAt`.

Пользователь представляет локальный тестовый Telegram user и чат. Один пользователь может открывать диалоги с разными ботами; конкретная пара пользователь-бот выбирается при открытии чата и не хранится в cookie, чтобы разные вкладки могли работать с разными диалогами.

Обязательные поля пользователя:

- `id`: внутренний id пользователя.
- `userId`: numeric id фейкового Telegram-пользователя.
- `username`: username фейкового Telegram-пользователя.
- `firstName`: имя фейкового Telegram-пользователя.
- `lastName`: необязательная фамилия фейкового Telegram-пользователя.
- `chatId`: numeric chat id. По умолчанию равен `userId` для private chats.
- `chatType`: изначально `private`; позже `group`, `supergroup`, `channel`.
- `enabled`: может ли пользователь отправлять updates.
- `createdAt`, `updatedAt`.

Ожидаемое поведение:

- Пользователь может создавать, редактировать, дублировать, удалять, импортировать и экспортировать ботов.
- Пользователь может создавать, редактировать, дублировать, удалять, импортировать и экспортировать пользователей.
- Пользователь может выбрать сохраненного пользователя и бота на экране чата.
- История диалога привязана к паре пользователь-бот.
- Для group/supergroup несколько пользователей могут иметь общий `chat_id`; выбранный пользователь является отправителем, а история чата читается по `bot_id + chat_id`.
- Некорректные webhook URL отклоняются до отправки в режиме webhook.

### 4.2 Chat UI

Возможности MVP:

- Список сообщений пользователя и бота.
- Поле ввода сообщения.
- Отправка кнопкой и клавишей Enter.
- Многострочный ввод через Shift+Enter.
- Индикатор текущего пользователя и бота.
- Статус доставки:
  - в очереди;
  - доставлено;
  - ошибка.
- Панель ошибок webhook.
- Raw payload inspector для выбранных сообщений.

Возможности следующих версий:

- Mock attachments: photo, document, location, venue, contact, dice, voice.
- Дальнейшее развитие group chat: отдельная сущность группы, title, membership, роли и service messages.

Решение по multi-bot UI: отдельный экран, где один пользователь одновременно общается с несколькими ботами, в текущий scope не входит. Причины:

- основной сценарий эмулятора — проверка конкретной пары пользователь-бот;
- delivery mode, pending queue, webhook attempts и inspector привязаны к конкретному боту;
- общий composer для нескольких ботов создает неоднозначность: сообщение должно попасть одному выбранному боту или всем ботам сразу;
- текущая URL-модель `/chat?profile_id=<ID>&bot_id=<ID>` уже позволяет открыть несколько пар в разных вкладках браузера без shared state.

Текущая альтернатива: открыть один и тот же `profile_id` с разными `bot_id` в разных вкладках или окнах. Возвращаться к multi-bot экрану стоит только при появлении сценария сравнения реакций нескольких ботов на один и тот же пользовательский ввод.

### 4.3 Генерация update

Для текстового сообщения пользователя генерировать Telegram-like update:

```json
{
  "update_id": 100000001,
  "message": {
    "message_id": 1,
    "date": 1779800000,
    "chat": {
      "id": 1001,
      "type": "private",
      "username": "dev_user",
      "first_name": "Dev",
      "last_name": "User"
    },
    "from": {
      "id": 1001,
      "is_bot": false,
      "username": "dev_user",
      "first_name": "Dev",
      "last_name": "User",
      "language_code": "en"
    },
    "text": "/start",
    "entities": [
      {
        "offset": 0,
        "length": 6,
        "type": "bot_command"
      }
    ]
  }
}
```

Правила:

- `update_id` монотонно растет глобально или в пределах пользователя.
- `message_id` монотонно растет в пределах чата.
- `date` использует текущий Unix timestamp.
- Команды, начинающиеся с `/`, должны включать entity `bot_command`.
- Сгенерированный payload должен быть виден в интерфейсе.

### 4.4 Доставка updates

Эмулятор должен поддерживать два режима доставки для каждого бота:

- `webhook`: update отправляется HTTP POST на `webhookUrl`.
- `long_polling`: update сохраняется в очередь и отдается через `getUpdates`.

Переключение режима доставки не должно требовать перезапуска контейнера эмулятора.

#### Webhook delivery

Требования к webhook delivery:

- Отправлять JSON payload методом `POST`.
- Устанавливать `Content-Type: application/json`.
- Добавлять `X-Telegram-Bot-Api-Secret-Token`, если он настроен.
- Сохранять request status, response status code, response headers, response body, duration и error message.
- Использовать настраиваемый timeout. Значение MVP по умолчанию: 10 секунд.
- Не делать автоматические retry в MVP; предоставить ручную повторную отправку.

#### Long Polling

Требования к Long Polling:

- Реализовать `GET|POST /bot{token}/getUpdates`.
- Поддержать параметры `offset`, `limit`, `timeout`, `allowed_updates`.
- Хранить очередь updates отдельно для каждого бота.
- Возвращать только updates с `update_id >= offset`, как в Telegram Bot API.
- После получения offset больше `update_id` считать соответствующие updates подтвержденными и скрывать их из следующих ответов.
- `timeout` в MVP можно реализовать через короткое ожидание на сервере; позже добавить более точное блокирующее ожидание.
- В интерфейсе показывать размер очереди Long Polling и последние выданные updates.

### 4.5 Локальный Bot API mock

Предоставить routes, совместимые с распространенными Telegram bot libraries:

```text
GET  /bot<TOKEN>/getMe
POST /bot<TOKEN>/sendMessage
POST /bot<TOKEN>/sendPhoto
POST /bot<TOKEN>/sendDocument
POST /bot<TOKEN>/sendLocation
POST /bot<TOKEN>/sendVenue
POST /bot<TOKEN>/sendContact
POST /bot<TOKEN>/sendDice
POST /bot<TOKEN>/editMessageText
POST /bot<TOKEN>/answerCallbackQuery
POST /bot<TOKEN>/setMyCommands
GET  /bot<TOKEN>/getMyCommands
POST /bot<TOKEN>/deleteMyCommands
POST /bot<TOKEN>/setWebhook
POST /bot<TOKEN>/deleteWebhook
GET  /bot<TOKEN>/getWebhookInfo
GET  /bot<TOKEN>/getUpdates
POST /bot<TOKEN>/getUpdates
```

Фигурные скобки не должны попадать в реальный URL. Эмулятор должен повторять форму настоящего Telegram Bot API: token идет сразу после `/bot`, без дополнительного `/` между `bot` и token.

Приоритет MVP:

1. `sendMessage`
2. `getMe`
3. `getUpdates`
4. `setWebhook`
5. `deleteWebhook`
6. `getWebhookInfo`

Поведение `sendMessage`:

- Проверять, что token соответствует известному боту.
- Принимать JSON и form-encoded requests, если это практично.
- Принимать текстовые поля `multipart/form-data` для совместимости с bot frameworks, которые отправляют Bot API параметры как multipart даже без файлов.
- Сохранять сообщение бота в истории диалога пользователя.
- Сохранять и возвращать `reply_markup` для `inline_keyboard` и `keyboard`, чтобы интерфейс чата мог показать кнопки.
- Возвращать Telegram-like response:

```json
{
  "ok": true,
  "result": {
    "message_id": 2,
    "date": 1779800001,
    "chat": {
      "id": 1001,
      "type": "private"
    },
    "text": "Hello"
  }
}
```

Текущий эмулятор реализует MVP-методы `getMe`, `getUpdates`, `sendMessage`, `sendPhoto`, `sendDocument`, `sendLocation`, `sendVenue`, `sendContact`, `sendDice`, `editMessageText`, `getWebhookInfo`, `setWebhook`, `deleteWebhook`, `setMyCommands`, `getMyCommands`, `deleteMyCommands` и `answerCallbackQuery`. `sendPhoto`/`sendDocument` в первой версии принимают только строковое/URL значение `photo`/`document` без файловых upload. `sendLocation`/`sendVenue`/`sendContact`/`sendDice` сохраняют structured payload в истории и возвращают соответствующие Telegram-like поля `Message`; `sendDice` использует детерминированное значение для стабильных локальных тестов. Остальные методы Telegram Bot API пока должны возвращать явный Telegram-like ответ `ok=false` с HTTP 501, а не молчаливую заглушку. Подробный список текущих ограничений ведется в `docs/limitations.md`.

Поведение команд и кнопок:

- `setMyCommands` сохраняет default-список команд для бота; scope и language-specific команды пока не разделяются.
- `/chat` показывает компактный выпадающий список команд рядом с полем ввода.
- `reply_markup.inline_keyboard` отображается под сообщением бота; кнопки с `callback_data` создают `callback_query` update, URL-кнопки открываются как ссылки.
- `reply_markup.keyboard` отображается как основная клавиатура чата; нажатие отправляет текст кнопки как обычное пользовательское сообщение.

### 4.6 Хранение

MVP может использовать локальную file-backed database.

Рекомендуемые варианты:

- SQLite для структурированных данных и простой Docker volume persistence.
- JSON files только для раннего prototype, но до расширения API coverage лучше перейти на SQLite.

Обязательные persistent entities:

- bots;
- users;
- messages;
- webhook delivery attempts;
- long polling update queue;
- emulator settings.

### 4.7 Import/export формат

Принятое решение: import/export использует JSON envelope с числовой `version`, временем экспорта `exported_at` и независимыми массивами сущностей. Архивный формат не нужен для текущего scope, потому что бинарные файлы и history export пока не поддерживаются.

Текущие endpoints:

```text
GET  /export/bots
GET  /export/profiles
POST /import/bots
POST /import/profiles
```

`GET /export/bots` возвращает:

```json
{
  "ok": true,
  "version": 1,
  "exported_at": "2026-06-04T12:00:00+10:00",
  "bots": [
    {
      "token": "123456:local-dev-token",
      "bot_id": 123456,
      "username": "local_bot",
      "display_name": "Local Bot",
      "delivery_mode": "long_polling",
      "webhook_url": null,
      "webhook_secret_token": null,
      "enabled": true
    }
  ]
}
```

`GET /export/profiles` возвращает:

```json
{
  "ok": true,
  "version": 1,
  "exported_at": "2026-06-04T12:00:00+10:00",
  "profiles": [
    {
      "user_id": 1001,
      "username": "dev_user",
      "first_name": "Dev",
      "last_name": "User",
      "chat_id": 1001,
      "chat_type": "private",
      "language_code": "ru",
      "enabled": true
    }
  ]
}
```

Import принимает как envelope с ключом `bots`/`profiles`, так и bare array соответствующих объектов. Envelope-форма считается предпочтительной для документации и будущей совместимости.

Стратегия конфликтов:

- импорт сначала валидирует весь payload и только потом создает записи;
- конфликт `token` у ботов возвращает HTTP 409;
- конфликт `user_id` у пользователей возвращает HTTP 409;
- конфликт private/channel `chat_id` у пользователей возвращает HTTP 409;
- несколько пользователей с `chat_type=group|supergroup` могут иметь общий `chat_id`, если этот `chat_id` не занят private/channel пользователем;
- дубликаты внутри одного import payload считаются конфликтом;
- некорректная структура JSON или невалидные поля возвращают HTTP 400;
- import не делает merge/update существующих записей, только создает новые;
- history, messages, updates, delivery attempts и logs не входят в текущий export/import.

Стратегия расширения:

- новые сущности добавляются отдельными top-level массивами, например `groups`, `bot_commands`, `messages`;
- связи между сущностями должны использовать стабильные доменные ключи (`token`, `user_id`, `chat_id`) или явно документированные external ids, а не внутренние SQLite `id`;
- при изменении несовместимого формата нужно увеличить `version`;
- бинарные файлы не добавляются в JSON напрямую; если появится media export, для него нужно отдельное решение по архиву и manifest.

### 4.8 Конфигурация

Переменные окружения:

- `APP_HOST`: bind host, по умолчанию `0.0.0.0`.
- `APP_PORT`: HTTP port, по умолчанию `8080`.
- `DATA_DIR`: директория данных, по умолчанию `/app/data`.
- `LOG_LEVEL`: по умолчанию `info`.
- `WEBHOOK_TIMEOUT_MS`: по умолчанию `10000`.

### 4.9 Docker

Обязательные deliverables:

- запуск через `docker-compose.yml` на готовом образе `php:8.3-cli-alpine`
- persistent volume для `/app/data`
- healthcheck endpoint: `GET /health`

Контейнер должен поддерживать development mode с source mount. Отдельная сборка образа через `Dockerfile` не требуется, пока проект не добавит расширения или runtime-зависимости, которых нет в готовом PHP-образе.

## 5. Архитектура

Рекомендуемые высокоуровневые компоненты:

- Web frontend: Telegram-like UI на HTMX, управление ботами и пользователями, payload inspector.
- Backend HTTP server:
  - API ботов;
  - API пользователей;
  - API сообщений;
  - webhook dispatcher;
  - Long Polling queue;
  - Bot API mock routes.
- Persistence layer: SQLite.
- Event/state layer: серверное состояние диалогов; для MVP достаточно HTMX polling, позже можно добавить SSE.

Runtime stack проекта:

- PHP 8.3+.
- HTMX для интерактивности интерфейса.
- SQLite для локального хранения.
- Docker Compose для запуска.
- Минимальный CSS без heavy frontend build pipeline на первом этапе.

## 6. Предлагаемые MVP milestones

### Milestone 1: каркас проекта

- Dockerized web app запускается локально.
- `GET /health` работает.
- Базовый UI shell открывается в браузере.
- SQLite storage инициализировано.

### Milestone 2: боты и пользователи

- CRUD API для ботов.
- CRUD API для пользователей.
- Выбор пользователя и бота на экране чата без cookie-состояния.
- Редактор пользователя.
- Сохранение ботов и пользователей.

### Milestone 3: Long Polling message loop

- Отправка текстового сообщения пользователя из UI.
- Генерация Telegram-like update.
- Сохранение update в очереди выбранного бота.
- Реализация `/bot{token}/getUpdates`.
- Отображение raw payload и состояния очереди.

### Milestone 4: webhook message loop

- Отправка текстового сообщения пользователя из UI.
- Генерация Telegram-like update.
- POST update на webhook URL выбранного бота.
- Отображение результата доставки и raw payload.

### Milestone 5: ответы бота

- Реализовать `/bot{token}/sendMessage`.
- Сохранять ответы бота.
- Показывать ответы бота в chat UI.

### Milestone 6: удобство разработки

- Docker Compose пример с sample bot.
- Import/export ботов и пользователей.
- Ручная повторная отправка failed webhook.
- Request/response inspector.

## 7. Риски и инженерные заметки

- Bot frameworks по-разному переопределяют Telegram Bot API base URL. Позже в документацию нужно добавить примеры для популярных frameworks.
- Некоторые боты зависят от API methods кроме `sendMessage`; неподдерживаемые методы должны возвращать понятные Telegram-like errors и логироваться.
- Webhook URL чувствителен к Docker network context. В Docker Compose URL обычно должен использовать service DNS, например `http://bot:3000/webhook`, а не `localhost`.
- Long Polling требует аккуратной модели подтверждения offset, иначе бот может получать дубликаты или терять updates.
- Решение по HTTP routing зафиксировано в `docs/adr-routing.md`: текущий custom router остается, а первичная модернизация должна декомпозировать `Application` на parser/handlers/services.
- Решение по тестам зафиксировано в `docs/adr-testing.md`: текущий самописный Docker HTTP smoke runner остается основным контуром, PHPUnit не добавляется без явной необходимости.
- Webhook delivery вынесена из `Application` в `WebhookDeliveryService`, Bot API handlers вынесены в `BotApiController`, Chat UI handlers вынесены в `ChatController`, parsing request body вынесен в `BotApiRequestParser`; дальнейшая декомпозиция должна аналогично уменьшать ответственность `Application` без изменения HTTP-контрактов.
- Полная совместимость с Telegram имеет большую поверхность. Эмулятор должен расти от реальных задач разработки ботов, а не от попытки сразу клонировать весь API.

## 8. Открытые вопросы

- Какие bot frameworks поддерживать первыми: Telegraf, aiogram, python-telegram-bot, grammY, Telegram.Bot for .NET?
- Для group chat нужна отдельная сущность группы или достаточно нескольких пользователей с одним `chatId` и режимом выбора отправителя?
- Интерфейс проекта и документация должны быть только на русском или двуязычными?

## 9. Ссылки

- Документация Telegram Bot API: https://core.telegram.org/bots/api
- Официальный Telegram Bot API server: https://github.com/tdlib/telegram-bot-api
- telegram-test-api: https://github.com/OvyFlash/telegram-test-api
- Telegraf-Test: https://github.com/TiagoDanin/Telegraf-Test
- Telemelya: https://github.com/luckyraul/telemelya

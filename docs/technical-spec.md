# Техническое задание: Telegram Bot Emulator

## 1. Цель

Создать Dockerized локальный инструмент разработки для Telegram-ботов.

Инструмент должен предоставлять минимальный Telegram-like веб-интерфейс и локальный webhook/Bot API эмулятор, чтобы разработчик мог тестировать поведение бота без настоящих Telegram-клиентов, публичных webhook, ngrok и production-состояния бота.

## 2. Целевой пользователь

Основной пользователь: backend-разработчик, который делает Telegram-ботов.

Типичный workflow:

1. Запустить контейнер бота и контейнер эмулятора через Docker Compose.
2. Открыть эмулятор в браузере.
3. Выбрать или создать тестовый профиль.
4. Настроить webhook URL, который указывает на контейнер бота.
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

### 4.1 Профили

Каждый профиль представляет локальный тестовый контекст.

Обязательные поля профиля:

- `id`: внутренний id профиля.
- `name`: отображаемое имя профиля в интерфейсе эмулятора.
- `botToken`: фейковый или похожий на настоящий token Telegram-бота.
- `botId`: numeric bot id, полученный из token, если возможно, или заданный вручную.
- `botUsername`: username бота без `@`.
- `userId`: numeric id фейкового Telegram-пользователя.
- `username`: username фейкового Telegram-пользователя.
- `firstName`: имя фейкового Telegram-пользователя.
- `lastName`: необязательная фамилия фейкового Telegram-пользователя.
- `chatId`: numeric chat id. По умолчанию равен `userId` для private chats.
- `chatType`: изначально `private`; позже `group`, `supergroup`, `channel`.
- `webhookUrl`: URL, который принимает сгенерированные updates.
- `webhookSecretToken`: необязательное значение, отправляемое как `X-Telegram-Bot-Api-Secret-Token`.
- `enabled`: может ли профиль отправлять updates.
- `createdAt`, `updatedAt`.

Ожидаемое поведение:

- Пользователь может создавать, редактировать, дублировать, удалять, импортировать и экспортировать профили.
- Пользователь может переключать профили из верхней части интерфейса.
- История диалога привязана к профилю.
- Некорректные webhook URL отклоняются до отправки.

### 4.2 Chat UI

Возможности MVP:

- Список сообщений пользователя и бота.
- Поле ввода сообщения.
- Отправка кнопкой и клавишей Enter.
- Многострочный ввод через Shift+Enter.
- Индикатор текущего профиля.
- Статус доставки:
  - в очереди;
  - доставлено;
  - ошибка.
- Панель ошибок webhook.
- Raw payload inspector для выбранных сообщений.

Возможности следующих версий:

- Рендеринг inline keyboard.
- Рендеринг reply keyboard.
- Нажатия на callback buttons.
- Mock attachments: photo, document, voice.
- Симуляция group chat.
- Несколько фейковых пользователей в одном чате.

### 4.3 Генерация webhook update

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

- `update_id` монотонно растет глобально или в пределах профиля.
- `message_id` монотонно растет в пределах чата.
- `date` использует текущий Unix timestamp.
- Команды, начинающиеся с `/`, должны включать entity `bot_command`.
- Сгенерированный payload должен быть виден в интерфейсе.

### 4.4 Webhook delivery

Требования к webhook delivery:

- Отправлять JSON payload методом `POST`.
- Устанавливать `Content-Type: application/json`.
- Добавлять `X-Telegram-Bot-Api-Secret-Token`, если он настроен.
- Сохранять request status, response status code, response headers, response body, duration и error message.
- Использовать настраиваемый timeout. Значение MVP по умолчанию: 10 секунд.
- Не делать автоматические retry в MVP; предоставить ручную повторную отправку.

### 4.5 Локальный Bot API mock

Предоставить routes, совместимые с распространенными Telegram bot libraries:

```text
GET  /bot{token}/getMe
POST /bot{token}/sendMessage
POST /bot{token}/editMessageText
POST /bot{token}/answerCallbackQuery
POST /bot{token}/setWebhook
GET  /bot{token}/getWebhookInfo
```

Приоритет MVP:

1. `sendMessage`
2. `getMe`
3. `setWebhook`
4. `getWebhookInfo`

Поведение `sendMessage`:

- Проверять, что token соответствует известному профилю или боту.
- Принимать JSON и form-encoded requests, если это практично.
- Сохранять сообщение бота в истории диалога профиля.
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

### 4.6 Хранение

MVP может использовать локальную file-backed database.

Рекомендуемые варианты:

- SQLite для структурированных данных и простой Docker volume persistence.
- JSON files только для раннего prototype, но до расширения API coverage лучше перейти на SQLite.

Обязательные persistent entities:

- profiles;
- messages;
- webhook delivery attempts;
- emulator settings.

### 4.7 Конфигурация

Переменные окружения:

- `APP_HOST`: bind host, по умолчанию `0.0.0.0`.
- `APP_PORT`: HTTP port, по умолчанию `8080`.
- `DATA_DIR`: директория данных, по умолчанию `/app/data`.
- `LOG_LEVEL`: по умолчанию `info`.
- `WEBHOOK_TIMEOUT_MS`: по умолчанию `10000`.

### 4.8 Docker

Обязательные deliverables:

- `Dockerfile`
- пример `docker-compose.yml`
- persistent volume для `/app/data`
- healthcheck endpoint: `GET /health`

Контейнер должен поддерживать development mode с source mount и production mode с собранными assets.

## 5. Архитектура

Рекомендуемые высокоуровневые компоненты:

- Web frontend: Telegram-like UI, управление профилями, payload inspector.
- Backend HTTP server:
  - API профилей;
  - API сообщений;
  - webhook dispatcher;
  - Bot API mock routes.
- Persistence layer: SQLite.
- Event/state layer: серверное состояние диалогов, опционально WebSocket/SSE для live updates.

Runtime stack нужно выбрать после настройки проекта. Практичные варианты:

- Node.js + Fastify/NestJS + React/Vite + SQLite.
- Python + FastAPI + HTMX/React + SQLite.

Для этого проекта сильный вариант по умолчанию: Node.js + Fastify + React/Vite, потому что многие Telegram bot frameworks используют JavaScript/TypeScript, а mock routes для Bot API реализуются прямо.

## 6. Предлагаемые MVP milestones

### Milestone 1: каркас проекта

- Dockerized web app запускается локально.
- `GET /health` работает.
- Базовый UI shell открывается в браузере.
- SQLite storage инициализировано.

### Milestone 2: профили

- CRUD API для профилей.
- Переключатель профиля.
- Редактор профиля.
- Сохранение профилей.

### Milestone 3: webhook message loop

- Отправка текстового сообщения пользователя из UI.
- Генерация Telegram-like update.
- POST update на webhook URL профиля.
- Отображение результата доставки и raw payload.

### Milestone 4: ответы бота

- Реализовать `/bot{token}/sendMessage`.
- Сохранять ответы бота.
- Показывать ответы бота в chat UI.

### Milestone 5: удобство разработки

- Docker Compose пример с sample bot.
- Import/export профилей.
- Ручная повторная отправка failed webhook.
- Request/response inspector.

## 7. Риски и инженерные заметки

- Bot frameworks по-разному переопределяют Telegram Bot API base URL. Позже в документацию нужно добавить примеры для популярных frameworks.
- Некоторые боты зависят от API methods кроме `sendMessage`; неподдерживаемые методы должны возвращать понятные Telegram-like errors и логироваться.
- Webhook URL чувствителен к Docker network context. В Docker Compose URL обычно должен использовать service DNS, например `http://bot:3000/webhook`, а не `localhost`.
- Полная совместимость с Telegram имеет большую поверхность. Эмулятор должен расти от реальных задач разработки ботов, а не от попытки сразу клонировать весь API.

## 8. Открытые вопросы

- Какие bot frameworks поддерживать первыми: Telegraf, aiogram, python-telegram-bot, grammY, Telegram.Bot for .NET?
- Должен ли профиль представлять одну пару bot-user или один bot должен иметь несколько user profiles?
- Поддерживать сначала только webhooks или также long polling через `getUpdates`?
- Интерфейс проекта и документация должны быть только на русском или двуязычными?

## 9. Ссылки

- Документация Telegram Bot API: https://core.telegram.org/bots/api
- Официальный Telegram Bot API server: https://github.com/tdlib/telegram-bot-api
- telegram-test-api: https://github.com/OvyFlash/telegram-test-api
- Telegraf-Test: https://github.com/TiagoDanin/Telegraf-Test
- Telemelya: https://github.com/luckyraul/telemelya


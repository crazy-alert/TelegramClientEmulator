# Telegram Bot Emulator

Локальный Docker-friendly эмулятор для разработки и тестирования Telegram-ботов офлайн.

Проект предоставляет минимальный Telegram-like веб-интерфейс, где разработчик может создавать профили пользователей, настраивать параметры бота и webhook, отправлять сообщения от имени тестовых пользователей и смотреть ответы бота без настоящих Telegram-аккаунтов и публичных webhook-туннелей.

## Проблема

Обычный workflow разработки Telegram-ботов создает лишнее трение:

- бот нужно регистрировать через BotFather;
- для проверки webhook часто нужен публичный HTTPS endpoint или tunnel;
- тестовые диалоги смешиваются с реальным состоянием Telegram;
- переключение между пользователями, чатами и конфигурациями бота занимает время;
- локальным Docker-сборкам нужны стабильные внутренние имена сервисов вместо публичных URL.

Эмулятор должен запускаться рядом с контейнером бота в Docker Compose и доставлять этому боту Telegram-совместимые webhook-обновления.

## Основная идея

Эмулятор работает как локальный Telegram-клиент и частичный симулятор Telegram-платформы:

1. Разработчик открывает веб-интерфейс эмулятора.
2. Активный профиль задает фейкового Telegram-пользователя, token бота, чат и webhook URL.
3. Разработчик вводит сообщение в интерфейсе чата.
4. Эмулятор создает похожий на Telegram Bot API payload `Update`.
5. Эмулятор отправляет payload на настроенный webhook URL.
6. Ответы бота можно принимать через поддерживаемые Bot API endpoints и показывать в интерфейсе.

## Scope MVP

### Веб-интерфейс

- Экран чата в стиле Telegram.
- Переключатель профилей в верхней части.
- Редактор профиля с полями:
  - название профиля;
  - token бота или bot id;
  - username бота;
  - user id;
  - username пользователя;
  - имя пользователя;
  - фамилия пользователя;
  - chat id;
  - webhook URL;
  - необязательный webhook secret token;
  - состояние включен/выключен.
- Поле ввода текстовых сообщений.
- История диалога отдельно для каждого профиля.
- Базовый статус доставки для каждого исходящего update.
- Инспектор raw update/request для отладки.

### Эмуляция webhook

- Генерировать похожие на Telegram объекты `Update` для:
  - текстовых сообщений;
  - команд вроде `/start`;
  - callback query на следующих этапах.
- Отправлять update на настроенный webhook URL с JSON body.
- Поддерживать настраиваемые заголовки, включая `X-Telegram-Bot-Api-Secret-Token`.
- Показывать статус ответа webhook, тело ответа и ошибки доставки.

### Mock Bot API

Предоставить минимальную локальную поверхность Bot API, чтобы контейнеры ботов могли обращаться к endpoints эмулятора вместо настоящего Telegram:

- `POST /bot{token}/sendMessage`
- `POST /bot{token}/editMessageText`
- `POST /bot{token}/answerCallbackQuery`
- `GET /bot{token}/getMe`
- `POST /bot{token}/setWebhook`
- `GET /bot{token}/getWebhookInfo`

В первой версии нужно отдать приоритет `sendMessage`, `getMe` и webhook-доставке, потому что они открывают основной локальный цикл разработки.

## Пример использования через Docker Compose

```yaml
services:
  telegram-emulator:
    build: .
    ports:
      - "8080:8080"
    volumes:
      - telegram-emulator-data:/app/data

  bot:
    build: ../my-bot
    environment:
      TELEGRAM_BOT_TOKEN: "123456:local-dev-token"
      TELEGRAM_API_BASE_URL: "http://telegram-emulator:8080"
      TELEGRAM_WEBHOOK_URL: "http://bot:3000/telegram/webhook"

volumes:
  telegram-emulator-data:
```

Точные имена переменных окружения зависят от bot framework. Некоторые frameworks позволяют напрямую переопределить базовый URL Telegram Bot API, другим может понадобиться небольшой adapter.

## Проверенные готовые проекты

Полного совпадения с нужным продуктом пока не найдено, но несколько проектов полезны как ориентиры:

- `telegram-test-api`: mock server Telegram Bot API для тестов.
- `Telegraf-Test`: testing helper для Telegraf-ботов.
- `Telemelya`: простой mock Telegram Bot API.
- Официальные Docker-образы `telegram-bot-api`: полезны для локального Bot API hosting, но не дают fake chat UI и offline user simulator.

Эмулятор должен использовать идею mock API, но основной фокус проекта: интерактивный веб-интерфейс и локальный Docker-to-Docker workflow webhook.

## Документация

- [Техническое задание](docs/technical-spec.md)


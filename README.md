# Telegram Bot Emulator

Локальный Docker-friendly эмулятор для разработки и тестирования Telegram-ботов офлайн.

Проект предоставляет минимальный Telegram-like веб-интерфейс, где разработчик может создавать тестовых пользователей, настраивать несколько ботов, выбирать режим доставки обновлений через webhook или Long Polling, отправлять сообщения от имени тестовых пользователей и смотреть ответы бота без настоящих Telegram-аккаунтов и публичных webhook-туннелей.

## Проблема

Обычный workflow разработки Telegram-ботов создает лишнее трение:

- бот нужно регистрировать через BotFather;
- для проверки webhook часто нужен публичный HTTPS endpoint или tunnel;
- тестовые диалоги смешиваются с реальным состоянием Telegram;
- переключение между пользователями, чатами и конфигурациями бота занимает время;
- локальным Docker-сборкам нужны стабильные внутренние имена сервисов вместо публичных URL.

Эмулятор должен запускаться рядом с контейнерами ботов в Docker Compose и доставлять Telegram-совместимые обновления через webhook или отдавать их через `getUpdates` для Long Polling.

## Основная идея

Эмулятор работает как локальный Telegram-клиент и частичный симулятор Telegram-платформы:

1. Разработчик открывает веб-интерфейс эмулятора.
2. Разработчик выбирает сохраненного пользователя и бота на экране чата.
3. Разработчик вводит сообщение в интерфейсе чата.
4. Эмулятор создает похожий на Telegram Bot API payload `Update`.
5. Эмулятор либо отправляет payload на настроенный webhook URL, либо кладет update в очередь Long Polling.
6. Ответы бота можно принимать через поддерживаемые Bot API endpoints и показывать в интерфейсе.

## Scope MVP

### Веб-интерфейс

- Экран чата в стиле Telegram.
- Список и редактор пользователей с полями:
  - user id;
  - username пользователя;
  - имя пользователя;
  - фамилия пользователя;
  - chat id;
  - webhook URL;
  - необязательный webhook secret token;
  - состояние включен/выключен.
- Поле ввода текстовых сообщений.
- История диалога отдельно для каждой пары пользователь-бот.
- Базовый статус доставки для каждого исходящего update.
- Инспектор raw update/request для отладки.

### Эмуляция доставки updates

- Генерировать похожие на Telegram объекты `Update` для:
  - текстовых сообщений;
  - команд вроде `/start`;
  - callback query на следующих этапах.
- Поддерживать webhook-доставку на настроенный URL с JSON body.
- Поддерживать Long Polling через `getUpdates`.
- Поддерживать настраиваемые заголовки, включая `X-Telegram-Bot-Api-Secret-Token`.
- Показывать статус ответа webhook, тело ответа, ошибки доставки и состояние очереди Long Polling.

### Mock Bot API

Предоставить минимальную локальную поверхность Bot API, чтобы контейнеры ботов могли обращаться к endpoints эмулятора вместо настоящего Telegram:

- `POST /bot<TOKEN>/sendMessage`
- `POST /bot<TOKEN>/editMessageText`
- `POST /bot<TOKEN>/answerCallbackQuery`
- `GET /bot<TOKEN>/getMe`
- `POST /bot<TOKEN>/setWebhook`
- `POST /bot<TOKEN>/deleteWebhook`
- `GET /bot<TOKEN>/getWebhookInfo`
- `GET|POST /bot<TOKEN>/getUpdates`

Фигурные скобки не являются частью URL. Например, для token `123456:local-dev-token` корректный запрос выглядит так: `http://telegram-emulator:8080/bot123456:local-dev-token/getMe`.

В первой версии нужно отдать приоритет `sendMessage`, `getMe`, `getUpdates`, `setWebhook` и webhook-доставке, потому что они открывают основные локальные циклы разработки.

## Пример использования через Docker Compose

Контейнер использует готовый образ `php:8.3-cli-alpine`, монтирует проект в `/app` и запускает встроенный PHP HTTP server. Отдельная сборка через `Dockerfile` не требуется.

```yaml
services:
  telegram-emulator:
    image: php:8.3-cli-alpine
    working_dir: /app
    command: >
      sh -lc 'php -c /app/php.ini -S "$${APP_HOST}:$${APP_PORT}" -t public public/index.php'
    environment:
      APP_HOST: "0.0.0.0"
      APP_PORT: "8080"
      DATA_DIR: "/app/data"
      LOG_DIR: "/app/var/logs"
      WEBHOOK_TIMEOUT_MS: "10000"
    expose:
      - "8080"
    ports:
      - "${HOST_PORT:-8080}:8080"
    volumes:
      - ./:/app
    networks:
      - app-backend

  bot:
    build: ../my-bot
    environment:
      TELEGRAM_API_BASE_URL: "http://telegram-emulator:8080"

networks:
  app-backend:
    external: true
    name: "${APP_BACKEND_NETWORK:-constr_app-backend}"
```

Token, bot id, username бота, transport mode и webhook URL настраиваются в интерфейсе эмулятора для каждого бота. В контейнер бота не нужно зашивать один общий token проекта: бот должен использовать тот token, который разработчик выбрал в конкретном тестовом сценарии.

Точные имена переменных окружения зависят от bot framework. Если библиотека сама добавляет `/bot<TOKEN>/<METHOD>` к API root, используйте `http://telegram-emulator:8080`. Если библиотека ожидает Telegram-style base URL до token и сама дописывает token без дополнительного `/`, используйте `http://telegram-emulator:8080/bot`. Не передавайте в HTTP-клиент URL с буквальными `{token}` или `{method}`: многие клиенты отклоняют такие строки как malformed URL.

`WEBHOOK_TIMEOUT_MS` задает начальный timeout webhook-доставки в миллисекундах. Если переменная не задана, используется `10000`. На панели `/` timeout можно переопределить через UI без изменения файлов; значение хранится в SQLite и должно быть в диапазоне `1000`–`60000` мс.

В режиме разработки весь проект монтируется в контейнер как `/app`. Поэтому изменения в `public`, `src`, `templates`, `migrations` и других директориях применяются без пересборки образа. SQLite-файл по умолчанию создается в локальной директории `data/`, которая игнорируется git.

## Docker Compose сценарии подключения бота

### Long Polling бот

Подходит для ботов, которые сами вызывают `getUpdates`. В интерфейсе эмулятора создайте бота с fake token, например `123456:local-dev-token`, и оставьте режим доставки `long_polling`.

```yaml
services:
  telegram-emulator:
    image: php:8.3-cli-alpine
    working_dir: /app
    command: >
      sh -lc 'php -c /app/php.ini -S "$${APP_HOST}:$${APP_PORT}" -t public public/index.php'
    environment:
      APP_HOST: "0.0.0.0"
      APP_PORT: "8080"
      DATA_DIR: "/app/data"
      LOG_DIR: "/app/var/logs"
    volumes:
      - ./:/app
    networks:
      - app-backend

  bot:
    build: ../my-bot
    environment:
      TELEGRAM_BOT_TOKEN: "123456:local-dev-token"
      TELEGRAM_API_BASE_URL: "http://telegram-emulator:8080"
    networks:
      - app-backend

networks:
  app-backend:
    name: "${APP_BACKEND_NETWORK:-telegram-dev}"
```

### Webhook бот

Подходит для ботов, которые принимают updates на HTTP endpoint внутри своей контейнерной сети. В интерфейсе эмулятора укажите webhook URL как service DNS, например `http://bot:3000/telegram/webhook`, а не `localhost`.

```yaml
services:
  telegram-emulator:
    image: php:8.3-cli-alpine
    working_dir: /app
    command: >
      sh -lc 'php -c /app/php.ini -S "$${APP_HOST}:$${APP_PORT}" -t public public/index.php'
    environment:
      APP_HOST: "0.0.0.0"
      APP_PORT: "8080"
      DATA_DIR: "/app/data"
      LOG_DIR: "/app/var/logs"
      WEBHOOK_TIMEOUT_MS: "10000"
    ports:
      - "8080:8080"
    volumes:
      - ./:/app
    networks:
      - app-backend

  bot:
    build: ../my-bot
    environment:
      TELEGRAM_BOT_TOKEN: "123456:local-dev-token"
      TELEGRAM_WEBHOOK_PATH: "/telegram/webhook"
    expose:
      - "3000"
    networks:
      - app-backend

networks:
  app-backend:
    name: "${APP_BACKEND_NETWORK:-telegram-dev}"
```

### Что важно про `localhost`

`localhost` внутри контейнера указывает на сам контейнер, а не на соседний сервис и не на хост-машину. Поэтому:

- бот обращается к эмулятору как `http://telegram-emulator:8080`;
- эмулятор доставляет webhook в контейнер бота как `http://bot:3000/...`;
- URL `http://localhost:8080` используйте из браузера на хосте, если порт проброшен через `ports`;
- если bot framework сам дописывает `/bot<TOKEN>/<METHOD>`, задавайте `TELEGRAM_API_BASE_URL=http://telegram-emulator:8080`; если framework ожидает префикс до token, используйте `http://telegram-emulator:8080/bot`.

## Локальный запуск Этапа 0

Если приложение должно быть доступно с хоста и из backend-сети:

```bash
docker compose up -d
```

Если фактическое имя сети отличается, например Docker Compose создал сеть с префиксом проекта:

```bash
APP_BACKEND_NETWORK=constr_app-backend docker compose up -d
```

После запуска:

- внутри Docker-сети: `http://telegram-emulator:8080`;
- на хосте: `http://localhost:8080` или порт из `HOST_PORT`;
- healthcheck: `/health`.

## Текущие возможности

- Панель состояния: `/`.
- Управление ботами: `/bots`.
- Управление пользователями: `/profiles`.
- Формы ботов и пользователей показывают основные ошибки рядом с полями и возвращают HTTP 422 без записи некорректных данных.
- Bot API: `GET|POST /bot<TOKEN>/getMe`.
- Bot API: `GET|POST /bot<TOKEN>/getUpdates` с `offset`, `limit`, `timeout` и `allowed_updates`.
- Bot API: `POST /bot<TOKEN>/sendMessage` сохраняет текстовый ответ бота в историю локального чата.
- Bot API: `sendMessage` поддерживает `reply_markup` для показа `inline_keyboard` и `keyboard` в интерфейсе чата.
- Bot API: `GET|POST /bot<TOKEN>/getWebhookInfo`.
- Bot API: `POST /bot<TOKEN>/setWebhook` и `POST /bot<TOKEN>/deleteWebhook`.
- Bot API: `POST /bot<TOKEN>/setMyCommands`, `GET|POST /bot<TOKEN>/getMyCommands`, `POST /bot<TOKEN>/deleteMyCommands`.
- Bot API: `POST /bot<TOKEN>/answerCallbackQuery` возвращает успешное подтверждение callback.
- Чат показывает сохраненные команды бота; команды, reply-кнопки и inline-кнопки кликабельны.
- Чат на `/chat` периодически обновляет историю, клавиатуры и inspector через HTMX-фрагмент `/chat/fragment?profile_id=<ID>&bot_id=<ID>`.
- В чате можно очистить историю и updates только выбранной пары пользователь-бот после явного подтверждения.
- Экран `/updates` показывает список updates с фильтрами по боту, пользователю, `queue_state` и `update_id`.
- Экран `/updates` позволяет очистить pending/confirmed updates выбранного бота после явного подтверждения.
- Экран `/request-inspector` показывает последние Bot API request/response из HTTP JSONL-логов и webhook request/response из delivery attempts; bot token и secret token маскируются в HTML-выводе.
- Экран `/import-export` экспортирует и импортирует JSON для bots/profiles без истории сообщений; импорт отклоняет конфликты `token`, `user_id` и private `chat_id`, но разрешает нескольким group/supergroup пользователям общий `chat_id`. Формат описан в `docs/technical-spec.md`.
- Групповой чат моделируется несколькими пользователями с одинаковым `chat_id` и `chat_type=group|supergroup`; выбранный пользователь в `/chat` является отправителем сообщения.
- Webhook delivery: при режиме `webhook` новые updates отправляются POST-запросом на настроенный URL, попытка доставки сохраняется и показывается в инспекторе последнего update.
- Timeout webhook delivery виден и настраивается на панели `/`; UI-настройка переопределяет `WEBHOOK_TIMEOUT_MS`.
- Failed webhook delivery можно повторить вручную из inspector последнего update.
- Отдельный экран `/delivery-attempts` показывает webhook delivery attempts с фильтрами по боту и `update_id`.
- Данные хранятся в SQLite в `data/telegram_emulator.sqlite`.
- HTTP-логи пишутся в JSONL-файлы `var/logs/http-YYYY-MM-DD.jsonl`; файлы старше 5 дней автоматически удаляются при обработке запросов.
- Контейнер доступен другим сервисам как `http://telegram-emulator:8080` в сети `APP_BACKEND_NETWORK`.

Ограничение inspector: `/request-inspector` читает последние локальные HTTP JSONL-логи и не является security boundary. Секреты маскируются в HTML-выводе, но сами runtime-логи остаются файлами разработки в `LOG_DIR`.

## Bot API и тесты

Текущая поддерживаемая поверхность Bot API:

- `GET|POST /bot<TOKEN>/getMe`;
- `GET|POST /bot<TOKEN>/getUpdates`;
- `POST /bot<TOKEN>/sendMessage`;
- `POST /bot<TOKEN>/sendPhoto`;
- `POST /bot<TOKEN>/sendDocument`;
- `POST /bot<TOKEN>/editMessageText`;
- `GET|POST /bot<TOKEN>/getWebhookInfo`;
- `POST /bot<TOKEN>/setWebhook`;
- `POST /bot<TOKEN>/deleteWebhook`;
- `POST /bot<TOKEN>/setMyCommands`;
- `GET|POST /bot<TOKEN>/getMyCommands`;
- `POST /bot<TOKEN>/deleteMyCommands`;
- `POST /bot<TOKEN>/answerCallbackQuery`.

Для `setWebhook` и других POST-методов поддерживаются JSON, `application/x-www-form-urlencoded` и текстовые поля `multipart/form-data`. Файловые части multipart-запросов пока игнорируются, потому что методы загрузки файлов в эмуляторе не реализованы.

`sendMessage` принимает `reply_markup` с `inline_keyboard` и `keyboard`. `sendPhoto` и `sendDocument` принимают строковое/URL значение `photo`/`document`, optional `caption` и `reply_markup`, сохраняют media placeholder в чате и возвращают Telegram-like `Message.photo`/`Message.document`; файловые upload в multipart пока не поддерживаются. `editMessageText` редактирует только сообщения бота по `chat_id` и `message_id`, поддерживает optional `reply_markup` и возвращает Telegram-like `Message`. Inline-кнопки с `callback_data` создают `callback_query` update через интерфейс чата, URL-кнопки открываются ссылкой, reply-кнопки отправляют обычный текстовый `message` update. Методы вне этой поверхности сейчас возвращают Telegram-like JSON с HTTP 501 и `ok=false`.

Подробный список ограничений, включая media upload, command scopes, language-specific commands, webhook retries и timeout, описан в [ограничениях эмулятора](docs/limitations.md).

Проверки Bot API запускаются в контейнере:

```bash
docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"
```

Синтаксис PHP-файлов:

```bash
docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"
```

## Проверенные готовые проекты

Полного совпадения с нужным продуктом пока не найдено, но несколько проектов полезны как ориентиры:

- `telegram-test-api`: mock server Telegram Bot API для тестов.
- `Telegraf-Test`: testing helper для Telegraf-ботов.
- `Telemelya`: простой mock Telegram Bot API.
- Официальные Docker-образы `telegram-bot-api`: полезны для локального Bot API hosting, но не дают fake chat UI и offline user simulator.

Эмулятор должен использовать идею mock API, но основной фокус проекта: интерактивный веб-интерфейс и локальный Docker-to-Docker workflow webhook.

## Документация

- [Техническое задание](docs/technical-spec.md)
- [Ограничения эмулятора](docs/limitations.md)
- [Примеры интеграции bot frameworks](docs/framework-examples.md)
- [Roadmap](ROADMAP.md)

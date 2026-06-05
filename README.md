# Telegram Bot Emulator

Telegram Bot Emulator — локальный эмулятор Telegram для разработки ботов. Он запускается у вас на компьютере, показывает веб-чат, создает Telegram-like `Update` и отдает их вашему боту через webhook или Long Polling. Настоящий Telegram, BotFather, публичный HTTPS и реальные пользователи для этого не нужны.

Проще говоря: это песочница, где можно писать и проверять Telegram-бота так, будто с ним общаются пользователи, но весь цикл остается внутри Docker и локальной машины.

## Для чего это нужно

Обычная разработка Telegram-бота часто упирается в лишние действия:

- нужно создавать тестового бота через BotFather;
- webhook требует публичный HTTPS-адрес или tunnel;
- тесты смешиваются с настоящими чатами Telegram;
- сложно быстро переключаться между пользователями, ботами, группами и сценариями;
- Docker-контейнеры должны знать внутренние адреса друг друга, а не случайные публичные URL.

Эмулятор убирает это трение:

- вы создаете fake bot token прямо в интерфейсе;
- создаете тестовых пользователей и group/supergroup чаты;
- отправляете сообщения из локального веб-чата;
- бот получает `Update` через `getUpdates` или webhook;
- ответы бота через локальные Bot API endpoints появляются в чате;
- запросы, ответы, ошибки webhook и payload можно смотреть в инспекторе.

## Что умеет сейчас

- Несколько ботов с отдельными token, bot id, username и режимом доставки.
- Тестовые пользователи и group/supergroup чаты.
- Telegram-like чат в браузере.
- Long Polling через `getUpdates`.
- Webhook-доставка в контейнер бота по Docker service URL.
- Локальные Bot API маршруты вида `/bot<TOKEN>/sendMessage`.
- Текст, команды, inline keyboard, reply keyboard и callback query.
- `editMessageText`, `answerCallbackQuery`, `setMyCommands`, `getMyCommands`, `deleteMyCommands`.
- Media и structured-сообщения: photo, document, video, animation, audio, voice, video note, sticker, poll, location, venue, contact, dice.
- Локальное media-хранилище и `getFile`.
- Инспектор Bot API request/response и webhook delivery attempts.
- Import/export тестовых ботов, пользователей, команд и fixture pack.
- Панель состояния, health endpoint и безопасная проверка обновлений.

Полная поддерживаемая Bot API surface описана в [`docs/bot-api-surface.json`](docs/bot-api-surface.json), ограничения — в [`docs/limitations.md`](docs/limitations.md).

## Как это работает

1. Вы открываете `http://localhost:8080`.
2. Создаете или выбираете тестового бота.
3. Создаете или выбираете тестового пользователя.
4. На вкладке `Чат` пишете сообщение.
5. Эмулятор создает Telegram-like `Update`.
6. Если бот работает через Long Polling, он получает update из `/bot<TOKEN>/getUpdates`.
7. Если бот работает через webhook, эмулятор отправляет POST на настроенный webhook URL.
8. Когда бот вызывает локальный Bot API, например `/bot<TOKEN>/sendMessage`, ответ появляется в чате.

## Установка на Windows

### 1. Установите Docker Desktop

1. Скачайте Docker Desktop: `https://www.docker.com/products/docker-desktop/`.
2. Установите его с включенной поддержкой WSL 2.
3. Перезагрузите Windows, если установщик попросит.
4. Запустите Docker Desktop и дождитесь статуса `Docker Desktop is running`.
5. Проверьте в PowerShell:

```powershell
docker --version
docker compose version
```

Если команды не найдены, перезапустите PowerShell или Windows.

### 2. Установите Git

1. Скачайте Git for Windows: `https://git-scm.com/download/win`.
2. Установите с настройками по умолчанию.
3. Проверьте в PowerShell:

```powershell
git --version
```

### 3. Скачайте проект

Выберите папку, где будут лежать локальные проекты, например `S:\` или `C:\Projects`:

```powershell
cd S:\
git clone https://github.com/crazy-alert/TelegramClientEmulator.git
cd TelegramClientEmulator
```

Если проект уже скачан, просто перейдите в его папку:

```powershell
cd S:\TelegramClientEmulator
```

### 4. Подготовьте Docker-сеть

По умолчанию `docker-compose.yml` ожидает внешнюю сеть `constr_app-backend`. Если такой сети нет, создайте ее:

```powershell
docker network create constr_app-backend
```

Если хотите использовать другое имя сети, создайте его и передавайте переменную `APP_BACKEND_NETWORK` при запуске:

```powershell
docker network create telegram-dev
$env:APP_BACKEND_NETWORK = "telegram-dev"
docker compose up -d
```

### 5. Запустите эмулятор

Стандартный запуск:

```powershell
docker compose up -d
```

Откройте в браузере:

```text
http://localhost:8080
```

Проверка состояния доступна по raw endpoint:

```text
http://localhost:8080/health
```

Остановить контейнер:

```powershell
docker compose down
```

Посмотреть логи:

```powershell
docker compose logs -f telegram-emulator
```

## Порты и локальные адреса

По умолчанию эмулятор слушает порт `8080` внутри контейнера и публикуется на `localhost:8080` на Windows.

Если порт `8080` занят, запустите на другом порту:

```powershell
$env:HOST_PORT = "18080"
docker compose up -d
```

Тогда веб-интерфейс будет доступен так:

```text
http://localhost:18080
```

Внутри Docker-сети адрес не меняется:

```text
http://telegram-emulator:8080
```

Это важное различие:

- `localhost:8080` — адрес для браузера на Windows;
- `telegram-emulator:8080` — адрес для контейнера вашего бота;
- `bot:3000` или похожее имя — адрес контейнера бота для webhook-доставки из эмулятора.

## Настройка через hosts

Если неудобно помнить порт, можно добавить локальное имя в файл hosts.

Откройте PowerShell от имени администратора и выполните:

```powershell
notepad C:\Windows\System32\drivers\etc\hosts
```

Добавьте строку:

```text
127.0.0.1 telegram-emulator.local
```

После этого адрес будет таким:

```text
http://telegram-emulator.local:8080
```

Важно: hosts задает только имя. Порт `:8080` все равно нужен, если вы не используете reverse proxy на 80/443 порту.

## Запуск без reverse proxy

Это самый простой вариант и он подходит большинству локальных сценариев.

1. Эмулятор открыт в браузере как `http://localhost:8080`.
2. Бот в Docker обращается к Bot API эмулятора как `http://telegram-emulator:8080`.
3. Если бот работает через webhook, в настройках бота в эмуляторе укажите URL контейнера бота, например:

```text
http://bot:3000/telegram/webhook
```

Не указывайте для webhook `http://localhost:3000/...`, если endpoint находится в контейнере бота. Для эмулятора `localhost` внутри контейнера означает сам контейнер эмулятора, а не контейнер бота.

Пример Long Polling бота:

```yaml
services:
  telegram-emulator:
    image: php:8.3-cli-alpine
    container_name: telegram-emulator
    working_dir: /app
    command: >
      sh -lc 'php -c /app/php.ini -S "$$APP_HOST:$$APP_PORT" -t public public/index.php'
    environment:
      APP_HOST: "0.0.0.0"
      APP_PORT: "8080"
      DATA_DIR: "/app/data"
      LOG_DIR: "/app/var/logs"
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
      TELEGRAM_API_BASE_URL: "http://telegram-emulator:8080"
    networks:
      - app-backend

networks:
  app-backend:
    name: telegram-dev
```

Пример webhook бота:

```yaml
services:
  telegram-emulator:
    image: php:8.3-cli-alpine
    container_name: telegram-emulator
    working_dir: /app
    command: >
      sh -lc 'php -c /app/php.ini -S "$$APP_HOST:$$APP_PORT" -t public public/index.php'
    environment:
      APP_HOST: "0.0.0.0"
      APP_PORT: "8080"
      DATA_DIR: "/app/data"
      LOG_DIR: "/app/var/logs"
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
    name: telegram-dev
```

В интерфейсе эмулятора для такого webhook бота укажите:

```text
http://bot:3000/telegram/webhook
```

## Запуск с reverse proxy

Reverse proxy нужен, если вы хотите красивые локальные адреса без портов или если ваш проект уже использует Nginx, Traefik, Caddy, Devilbox, DDEV, Laravel Sail, Symfony Docker или другой локальный proxy.

Есть два разных направления трафика:

- браузер Windows -> reverse proxy -> эмулятор;
- контейнеры внутри Docker -> эмулятор или бот по service DNS.

Для браузера можно сделать адрес:

```text
http://telegram-emulator.local
```

Для контейнеров лучше оставить внутренний Docker-адрес:

```text
http://telegram-emulator:8080
```

Пример Nginx server block на хосте или в proxy-контейнере:

```nginx
server {
    listen 80;
    server_name telegram-emulator.local;

    location / {
        proxy_pass http://telegram-emulator:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Если reverse proxy работает на Windows-хосте, а не в Docker-сети, `proxy_pass` обычно будет таким:

```nginx
proxy_pass http://127.0.0.1:8080;
```

Если reverse proxy работает в Docker, он должен быть подключен к той же сети, что и `telegram-emulator`, и тогда можно использовать:

```nginx
proxy_pass http://telegram-emulator:8080;
```

Для hosts при reverse proxy обычно добавляют:

```text
127.0.0.1 telegram-emulator.local
```

После этого браузер открывает `http://telegram-emulator.local`, а боты в Docker по-прежнему используют `http://telegram-emulator:8080`.

## Подключение вашего бота

### Если бот использует Long Polling

1. В эмуляторе откройте `Боты`.
2. Создайте бота с fake token, например `123456:local-dev-token`.
3. Оставьте delivery mode `long_polling`.
4. В настройках вашего бота задайте:

```text
TELEGRAM_BOT_TOKEN=123456:local-dev-token
TELEGRAM_API_BASE_URL=http://telegram-emulator:8080
```

Некоторые bot frameworks ожидают base URL без `/bot<TOKEN>`, а некоторые сами склеивают путь иначе. Правило простое:

- если библиотека сама добавляет `/bot<TOKEN>/<METHOD>`, задавайте `http://telegram-emulator:8080`;
- если библиотека ожидает префикс прямо перед token, задавайте `http://telegram-emulator:8080/bot`;
- не передавайте URL с буквальными `{token}` и `{method}`.

### Если бот использует webhook

1. В эмуляторе откройте `Боты`.
2. Создайте бота с fake token.
3. Выберите delivery mode `webhook`.
4. В поле webhook URL укажите адрес endpoint контейнера бота, например:

```text
http://bot:3000/telegram/webhook
```

5. Если ваш бот проверяет secret token, заполните webhook secret token.
6. Отправьте сообщение во вкладке `Чат`.
7. Если доставка не прошла, откройте `Inspector`, `Updates` или `Webhook attempts`.

## Настройки окружения

Основные переменные `docker-compose.yml`:

- `HOST_PORT` — порт на Windows-хосте, по умолчанию `8080`.
- `APP_BACKEND_NETWORK` — Docker-сеть, по умолчанию `constr_app-backend`.
- `DATA_DIR` — директория runtime-данных внутри контейнера, по умолчанию `/app/data`.
- `MEDIA_DIR` — директория media-файлов, по умолчанию `/app/data/media`.
- `MEDIA_MAX_BYTES` — максимальный размер одного media-файла, по умолчанию `10485760`.
- `LOG_DIR` — директория HTTP JSONL-логов, по умолчанию `/app/var/logs`.
- `WEBHOOK_TIMEOUT_MS` — начальный timeout webhook-доставки, по умолчанию `10000`.
- `WEBHOOK_RETRY_MAX_ATTEMPTS` — начальное число dev retry попыток, по умолчанию `1`.
- `WEBHOOK_RETRY_DELAY_MS` — задержка dev retry, по умолчанию `0`.
- `LONG_POLLING_MAX_TIMEOUT_SECONDS` — максимум ожидания `getUpdates.timeout`, по умолчанию `3`.
- `TELEGRAM_EMULATOR_UPDATE_CHECK_URL` — необязательный URL проверки обновлений, по умолчанию GitHub API текущего репозитория.

Webhook timeout и development retry можно менять на вкладке `Панель` без редактирования файлов. Значения сохраняются в SQLite.

## Данные и логи

При стандартном запуске проект монтируется в контейнер как `/app`.

Runtime-данные лежат локально:

- SQLite: `data/telegram_emulator.sqlite`;
- media: `data/media/`;
- HTTP-логи: `var/logs/http-YYYY-MM-DD.jsonl`.

Эти файлы не коммитятся в git. Обычный `git pull` не должен удалять ваши локальные тестовые данные.

## Обновление проекта

Вкладка `Панель` содержит кнопку `Проверить обновления TelegramEmulator`.

Механизм безопасный:

- PHP не запускает `git`;
- PHP не выполняет команды операционной системы;
- локальный commit hash читается из `version.json`;
- последняя версия проверяется по HTTPS через удаленный `version.json` или URL из `TELEGRAM_EMULATOR_UPDATE_CHECK_URL`;
- если commit отличается, интерфейс показывает ручные команды обновления.

Обновить проект вручную:

```powershell
cd S:\TelegramClientEmulator
git pull
docker compose pull
docker compose up -d
```

Если вы запускали проект с переменными окружения, например другим портом или сетью, используйте их снова:

```powershell
$env:HOST_PORT = "18080"
$env:APP_BACKEND_NETWORK = "telegram-dev"
docker compose up -d
```

## Проверка и тесты

Основной smoke runner:

```powershell
docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"
```

Focused tests:

```powershell
docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php && php tests/chat_repository_test.php"
```

Проверка синтаксиса PHP:

```powershell
docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"
```

## Текущие ограничения

- Это локальный инструмент разработки, а не настоящий Telegram server.
- Эмулятор не подключается к настоящему Telegram.
- Поддерживается только та Bot API surface, которая нужна для локальных сценариев проекта.
- Неподдерживаемые методы возвращают Telegram-like JSON с HTTP 501 и `ok=false`.
- Development retry webhook выполняется синхронно в текущем HTTP-запросе и не является production scheduler.
- Встроенный PHP server однопроцессный, поэтому Long Polling timeout ограничен.
- Inspector маскирует секреты в HTML, но runtime-логи остаются локальными файлами разработки.

## Документация

- [Roadmap](ROADMAP.md)
- [Техническое задание](docs/technical-spec.md)
- [Ограничения эмулятора](docs/limitations.md)
- [Примеры интеграции bot frameworks](docs/framework-examples.md)
- [ADR: HTTP routing и micro-framework](docs/adr-routing.md)
- [ADR: стратегия тестирования](docs/adr-testing.md)
- [Чеклист актуализации Roadmap](docs/roadmap-update-checklist.md)

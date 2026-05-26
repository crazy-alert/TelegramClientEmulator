# Текущий контекст проекта

## Состояние

Проект: локальный эмулятор Telegram Bot API для разработки и тестирования ботов.

Реализовано:

- Docker runtime на PHP 8.3 CLI Alpine.
- Nginx reverse proxy перед `php -S` для фильтрации warning встроенного PHP-сервера при некорректном `multipart/form-data`.
- SQLite bootstrap и миграция `001_initial_schema.sql`.
- CRUD ботов и профилей через server-rendered PHP templates.
- Экран чата (`/chat`), отправка сообщений, генерация Telegram-like `Update`, inspector raw payload.
- Переключатели активного профиля и бота в шапке через cookie.
- Bot API: `GET|POST /bot{token}/getMe`.
- Bot API: `POST /bot{token}/setWebhook` сохраняет webhook URL, optional `secret_token` и переключает бота в `delivery_mode=webhook`; пустой `url` очищает webhook и возвращает `long_polling`.

## Важные решения

- Основной язык: PHP, стиль K&R для фигурных скобок.
- Интерактивность интерфейса: server-rendered PHP templates, HTMX планируется дальше.
- Хранение: SQLite в `data/telegram_emulator.sqlite`.
- Активный профиль/бот: cookie (MVP), позже можно перенести в SQLite settings.
- Проект не привязан к одному bot token.
- Обязательны оба режима получения updates: webhook и Long Polling.
- Для локального Docker workflow `setWebhook` принимает `http` и `https` URL, включая service DNS вроде `http://bot:3000/webhook`.

## Проверки

- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка в контейнере через `docker run -p 127.0.0.1:18081:8080 ...`:
  - создание тестового бота через `/bots`;
  - `POST /bot123456:local-dev-token/setWebhook` с `url=http://bot:3000/webhook&secret_token=test-secret` вернул `{"ok":true,"result":true}`;
  - `url=not-a-url` вернул HTTP 400;
  - SQLite содержит `delivery_mode=webhook`, `webhook_url=http://bot:3000/webhook`, `webhook_secret_token=test-secret`.

## Замечания

- После последних коммитов другого агента `nginx.conf` слушает фиксированный порт `8080`; переменная `APP_PORT` больше фактически не меняет порт nginx. Это стоит исправить отдельно или задокументировать как фиксированный внутренний порт.
- На хосте нет `php` в PATH, проверки PHP выполнялись внутри Docker.

## Ближайший следующий этап

Long Polling: `GET|POST /bot{token}/getUpdates`, поддержка `offset`, `limit`, `timeout`, подтверждение updates.

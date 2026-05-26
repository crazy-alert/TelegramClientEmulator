# Текущий контекст проекта

## Состояние

Проект находится на раннем этапе разработки локального эмулятора Telegram Bot API.

Реализовано:

- Docker runtime на PHP 8.3 CLI Alpine.
- Bind mount проекта в `/app`.
- Подключение к внешней Docker-сети через `APP_BACKEND_NETWORK`, по умолчанию `constr_app-backend`.
- Health endpoint `/health`.
- SQLite bootstrap и миграция `001_initial_schema.sql`.
- Базовый CRUD ботов и профилей через server-rendered PHP templates.

## Важные решения

- Основной язык: PHP.
- Интерактивность интерфейса: HTMX.
- Хранение: SQLite в `data/telegram_emulator.sqlite`.
- Проект не привязан к одному bot token.
- Обязательны оба режима получения updates: webhook и Long Polling через `getUpdates`.
- Docker Compose должен оставаться простым: один `docker-compose.yml`, без лишних override-файлов.

## Проверки, выполненные ранее

- PHP lint проходил по файлам `public`, `src`, `templates`.
- `nginx-proxy-manager` видел `http://telegram-emulator:8080/health` в сети `constr_app-backend`.
- `/bots` отдавал HTTP 200 через Docker-сеть.

## Ближайший следующий этап

Этап 2: базовый чат и генерация Telegram-like `Update`, затем очередь Long Polling для `getUpdates`.


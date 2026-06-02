# Карта проекта

## Корень

- `AGENTS.md` — постоянные правила работы AI-ассистентов.
- `AI_CURRENT_CONTEXT.md` — короткий текущий снимок состояния проекта.
- `AI_WORK_PLAN.md` — активная задача и ближайшие шаги.
- `AI_WORK_PLAN_COMPLITED.md` — архив завершенных чеклистов.
- `README.md` — пользовательская документация.
- `ROADMAP.md` — план реализации по этапам.
- `docker-compose.yml` — основной запуск приложения на готовом образе `php:8.3-cli-alpine`.
- `php.ini` — настройки PHP runtime для встроенного сервера.

## Приложение

- `public/index.php` — front controller.
- `src/Application.php` — маршрутизация и orchestration HTTP-запросов.
- `src/Database.php` — подключение SQLite.
- `src/MigrationRunner.php` — применение SQL-миграций.
- `src/BotRepository.php` — доступ к данным ботов.
- `src/BotCommandRepository.php` — хранение команд Bot API для `setMyCommands`/`getMyCommands`.
- `src/ProfileRepository.php` — доступ к данным пользователей (исторически таблица `profiles`).
- `src/Response.php` — HTTP response helpers.
- `src/View.php` — рендеринг шаблонов.

## Шаблоны

- `templates/layout.php` — общий HTML layout и базовые стили.
- `templates/dashboard.php` — панель состояния.
- `templates/bots/` — список и форма ботов.
- `templates/profiles/` — список и форма пользователей (исторически путь `/profiles`).

## Данные

- `migrations/001_initial_schema.sql` — базовая схема SQLite.
- `migrations/002_bot_commands.sql` — таблица команд бота.
- `data/` — локальные runtime-данные, игнорируются git.
- `var/` — служебная runtime-директория.

## Тесты

- `tests/bot_api_test.php` — интеграционный тест Bot API через встроенный PHP HTTP server.

## Документация

- `docs/technical-spec.md` — техническое задание.


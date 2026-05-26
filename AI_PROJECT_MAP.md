# Карта проекта

## Корень

- `AGENTS.md` — постоянные правила работы AI-ассистентов.
- `AI_CURRENT_CONTEXT.md` — короткий текущий снимок состояния проекта.
- `AI_WORK_PLAN.md` — активная задача и ближайшие шаги.
- `AI_WORK_PLAN_COMPLITED.md` — архив завершенных чеклистов.
- `README.md` — пользовательская документация.
- `ROADMAP.md` — план реализации по этапам.
- `Dockerfile` — runtime-образ PHP с SQLite.
- `docker-compose.yml` — основной запуск приложения.

## Приложение

- `public/index.php` — front controller.
- `src/Application.php` — маршрутизация и orchestration HTTP-запросов.
- `src/Database.php` — подключение SQLite.
- `src/MigrationRunner.php` — применение SQL-миграций.
- `src/BotRepository.php` — доступ к данным ботов.
- `src/ProfileRepository.php` — доступ к данным профилей.
- `src/Response.php` — HTTP response helpers.
- `src/View.php` — рендеринг шаблонов.

## Шаблоны

- `templates/layout.php` — общий HTML layout и базовые стили.
- `templates/dashboard.php` — панель состояния.
- `templates/bots/` — список и форма ботов.
- `templates/profiles/` — список и форма профилей.

## Данные

- `migrations/001_initial_schema.sql` — базовая схема SQLite.
- `data/` — локальные runtime-данные, игнорируются git.
- `var/` — служебная runtime-директория.

## Документация

- `docs/technical-spec.md` — техническое задание.


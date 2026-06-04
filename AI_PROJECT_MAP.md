# Карта проекта

## Корень

- `AGENTS.md` — постоянные правила работы AI-ассистентов.
- `AI_CURRENT_CONTEXT.md` — короткий текущий снимок состояния проекта.
- `AI_WORK_PLAN.md` — активная задача и ближайшие шаги.
- `AI_WORK_PLAN_COMPLITED.md` — архив завершенных чеклистов.
- `AI_PROPOSALS.md` — предложения по модернизации после анализа кода при пустой очереди задач.
- `README.md` — пользовательская документация.
- `ROADMAP.md` — план реализации по этапам.
- `docker-compose.yml` — основной запуск приложения на готовом образе `php:8.3-cli-alpine`.
- `php.ini` — настройки PHP runtime для встроенного сервера.

## Приложение

- `public/index.php` — front controller.
- `src/Application.php` — маршрутизация, orchestration HTTP-запросов и серверная валидация UI-форм.
- `src/Database.php` — подключение SQLite.
- `src/MigrationRunner.php` — применение SQL-миграций.
- `src/BotRepository.php` — доступ к данным ботов.
- `src/BotCommandRepository.php` — хранение команд Bot API для `setMyCommands`/`getMyCommands`.
- `src/UpdateRepository.php` — очередь updates, выборка pending updates, UI-список updates с context и очистка updates.
- `src/MessageRepository.php` — история сообщений диалогов, group/supergroup выборка по `bot_id + chat_id` и очистка сообщений выбранного диалога.
- `src/ProfileRepository.php` — доступ к данным пользователей (исторически таблица `profiles`).
- `src/DeliveryAttemptRepository.php` — webhook delivery attempts и UI-выборки с context.
- `src/HttpLogger.php` — запись HTTP request/response событий в JSONL.
- `src/HttpLogRepository.php` — read-only выборка Bot API request/response из HTTP JSONL-логов для inspector.
- `src/Response.php` — HTTP response helpers.
- `src/SettingsRepository.php` — чтение и запись локальных настроек приложения из таблицы `settings`.
- `src/View.php` — рендеринг полных шаблонов и partial-шаблонов без layout для HTMX.

## Шаблоны

- `templates/layout.php` — общий HTML layout и базовые стили.
- `templates/dashboard.php` — панель состояния.
- `templates/chat/index.php` — чат пользователя с ботом, история сообщений, keyboards и inspector последнего update; в режиме `chatFragment` рендерит HTMX-фрагмент без формы выбора.
- `templates/updates/` — список updates с фильтрами по боту, пользователю, `queue_state` и `update_id`.
- `templates/request-inspector/` — inspector Bot API HTTP logs и webhook delivery request/response с маскированием секретов.
- `templates/import-export/` — экран JSON export/import для bots и profiles без истории.
- `templates/bots/` — список и форма ботов.
- `templates/profiles/` — список и форма пользователей (исторически путь `/profiles`).
- `templates/delivery-attempts/` — список webhook delivery attempts с фильтрами.

## Данные

- `migrations/001_initial_schema.sql` — базовая схема SQLite.
- `migrations/002_bot_commands.sql` — таблица команд бота.
- `data/` — локальные runtime-данные, игнорируются git.
- `var/` — служебная runtime-директория.

## Тесты

- `tests/bot_api_test.php` — интеграционный тест Bot API через встроенный PHP HTTP server.

## Документация

- `docs/technical-spec.md` — техническое задание.
- `docs/limitations.md` — поддерживаемая Bot API поверхность и текущие ограничения эмулятора.
- `docs/framework-examples.md` — примеры подключения PHP, Python и Node.js bot frameworks к локальному Bot API эмулятора через Docker Compose service DNS.
- `docs/adr-routing.md` — архитектурное решение по custom router и отказу от micro-framework на текущем этапе.


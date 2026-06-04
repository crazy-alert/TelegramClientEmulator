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
- `src/Application.php` — composition root, custom router, orchestration UI/local routes и серверная валидация UI-форм; локальные Bot API requests делегируются в `BotApiController`.
- `src/BotApiController.php` — локальные Telegram Bot API маршруты `/bot<TOKEN>/<METHOD>`, parsing параметров, Telegram-like responses и handlers методов `getMe`, `getUpdates`, `sendMessage`, `sendPhoto`, `sendDocument`, `editMessageText`, webhook commands, bot commands и `answerCallbackQuery`.
- `src/BotApiRequestParser.php` — parser JSON, `application/x-www-form-urlencoded` и текстовых multipart fields при отключенном `enable_post_data_reading`.
- `src/ChatController.php` — UI-маршруты `/chat`, `/chat/fragment`, `/chat/send`, `/chat/callback`, `/chat/clear`, формирование данных для шаблона чата, создание message/callback updates и запуск webhook delivery для chat-сценариев.
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
- `src/ReplyMarkup.php` — общий helper для Bot API `reply_markup`: чтение Bot API параметра, кодирование в `messages.raw_payload`, извлечение markup из сообщения и вычисление актуальной reply keyboard.
- `src/SettingsRepository.php` — чтение и запись локальных настроек приложения из таблицы `settings`.
- `src/WebhookDeliveryService.php` — одна попытка webhook-доставки update, сохранение delivery attempt и обновление состояния очереди.
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

- `tests/bot_api_test.php` — entrypoint интеграционного теста Bot API через встроенный PHP HTTP server.
- `tests/support/test_helpers.php` — assertions, HTTP client helpers, form/multipart helpers и управление тестовыми runtime/server процессами.
- `tests/scenarios/unit_scenarios.php` — базовые unit-проверки `UpdateGenerator`.
- `tests/scenarios/http_scenarios.php` — HTTP-сценарии UI, Bot API, SQLite runtime, webhook delivery, Long Polling и import/export.
- `tests/request_parser_test.php` — focused tests parser для JSON, form-urlencoded, multipart text fields, пустого тела и malformed JSON.
- `tests/reply_markup_test.php` — focused tests helper `ReplyMarkup` для inline keyboard, reply keyboard, чтения из `raw_payload` и `remove_keyboard`.

## Документация

- `docs/technical-spec.md` — техническое задание.
- `docs/limitations.md` — поддерживаемая Bot API поверхность и текущие ограничения эмулятора.
- `docs/framework-examples.md` — примеры подключения PHP, Python и Node.js bot frameworks к локальному Bot API эмулятора через Docker Compose service DNS.
- `docs/adr-routing.md` — архитектурное решение по custom router и отказу от micro-framework на текущем этапе.
- `docs/adr-testing.md` — архитектурное решение по тестовой стратегии и текущему Docker HTTP smoke runner.


# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task10-command-scopes-language.md`.

Что сделано:

- Добавлена миграция `004_bot_command_scopes.sql`: `bot_commands` теперь хранит `scope_type`, `scope_key`, raw `scope_json` и `language_code`.
- Старые команды мигрируются в default scope с пустым `language_code`.
- `setMyCommands`, `getMyCommands` и `deleteMyCommands` работают с exact `scope + language_code`; без параметров используется default scope.
- `BotApiParams` валидирует стандартные Bot API command scopes и нормализует `language_code`.
- UI `/chat` выбирает релевантный набор команд для текущего profile/chat: `chat_member`, `chat`, `all_private_chats` или `all_group_chats`, затем `default`; exact `language_code` приоритетнее пустого.
- Fixture pack import/export сохраняет `scope` и `language_code` для `bot_commands`.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/BotCommandRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotApiParams.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotApiController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_params_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_payload_factory_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/long_polling_service_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`
- `task13.md` — новый untracked task-файл; обработать по алфавиту после task12, если он останется в очереди.

Следующий шаг: взять `.aitasks/task11-http-log-inspector.md`, обновить `AI_WORK_PLAN.md` и реализовать ее отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- Admin-only command scopes сохраняются и возвращаются exact Bot API запросами, но роли администраторов в UI пока не моделируются.
- `getUpdates.timeout` в single-process PHP server ограничивается `LONG_POLLING_MAX_TIMEOUT_SECONDS`.
- Import/export v1 для bots/profiles остается стабильным; fixture pack v2 использует top-level массивы и не встраивает бинарные media.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.

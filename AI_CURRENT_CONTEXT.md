# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task08-fixture-packs-import-export.md`.

Что сделано:

- Добавлены отдельные endpoints fixture pack v2: `GET /export/fixture-pack` и `POST /import/fixture-pack`.
- Старые v1 endpoints `/export/bots`, `/export/profiles`, `/import/bots`, `/import/profiles` оставлены без изменения формата.
- Fixture pack экспортирует `bots`, `profiles`, `chats`, `bot_commands` и `media_manifest` с `included=false`.
- Импорт fixture pack валидирует конфликты до записи, создает новых bots/profiles, сохраняет `chats` metadata и импортирует `bot_commands` по стабильному `bot_token`.
- `BotCommandRepository` получил export групп команд по token, `ChatRepository` получил список chats и upsert metadata.
- Добавлен HTTP-сценарий `tests/scenarios/fixture_pack_scenarios.php` для conflict и round-trip проверок.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и UI `/import-export`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotCommandRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/fixture_pack_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_params_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_payload_factory_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/long_polling_service_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`
- `task13.md` — новый untracked task-файл, не относится к task08; обработать по алфавиту после task12, если он останется в очереди.

Следующий шаг: взять `.aitasks/task09-long-polling-timeout-model.md`, обновить `AI_WORK_PLAN.md` и реализовать ее отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Import/export v1 для bots/profiles остается стабильным и не содержит историю, messages, updates, delivery attempts или бинарные media.
- Fixture pack v2 предназначен для повторяемых dev/test сценариев; связи используют стабильные ключи `token`, `bot_token`, `user_id`, `chat_id`, а не SQLite `id`.
- Бинарные media не встраиваются в JSON fixture pack; текущий `media_manifest` только явно фиксирует `included=false`.
- Group chat модель нормализована через `chats/chat_members`, но `profiles.chat_id/chat_type` остаются совместимой поверхностью UI и import/export.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.

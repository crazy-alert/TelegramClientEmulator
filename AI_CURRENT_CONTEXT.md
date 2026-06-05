# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task02-import-export-controller.md`.

Что сделано:

- Добавлен `src/ImportExportController.php` для `/import-export`, `/export/*`, `/import/*`, JSON validation и fixture pack v2.
- `Application` теперь делегирует import/export новому контроллеру и больше не содержит import/export helper logic.
- `public/index.php`, `AI_PROJECT_MAP.md` и `docs/technical-spec.md` обновлены под новую архитектуру.
- В `tests/scenarios/import_export_scenarios.php` добавлена проверка конфликта `chat_id`.
- В `tests/scenarios/fixture_pack_scenarios.php` добавлены проверки bot_commands section и отказа от JSON fixture pack с бинарными media.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/ImportExportController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l public/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/import_export_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/fixture_pack_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task03-bot-api-route-registry.md`
- `task04-chat-all-attachments-ui.md`
- `task05-group-chat-admin-ui.md`
- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task03-bot-api-route-registry.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

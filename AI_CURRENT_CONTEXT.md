# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task03-bot-api-route-registry.md`.

Что сделано:

- В `src/BotApiController.php` добавлен route registry с canonical method name, HTTP verbs, handler и `media_field` для typed media.
- `BotApiController::handle()` теперь парсит `/bot<TOKEN>/<METHOD>` один раз и делегирует handler через registry.
- Сохранено старое поведение: case-insensitive method lookup и HTTP 501 для unknown method или неподходящего HTTP verb.
- `tests/bot_api_surface_catalog_test.php` теперь сверяет `docs/bot-api-surface.json` с `BotApiController::routeDefinitions()`, включая HTTP verbs.
- `AI_PROJECT_MAP.md` обновлен под route registry.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/BotApiController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/bot_api_surface_catalog_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_surface_catalog_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task04-chat-all-attachments-ui.md`
- `task05-group-chat-admin-ui.md`
- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task04-chat-all-attachments-ui.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

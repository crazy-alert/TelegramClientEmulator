# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task01-admin-controllers.md`.

Что сделано:

- Добавлен `src/BotAdminController.php` для `/bots`, `/bots/new`, `/bots/{id}/edit`, create/update/delete и validation формы бота.
- Добавлен `src/ProfileAdminController.php` для `/profiles`, `/profiles/new`, `/profiles/{id}/edit`, create/update/delete и validation формы пользователя.
- `Application` теперь делегирует admin UI ботов и пользователей новым контроллерам; import/export validation helpers оставлены в `Application` до task02.
- `public/index.php`, `AI_PROJECT_MAP.md` и `docs/technical-spec.md` обновлены под новую архитектуру.
- В `tests/scenarios/http_setup_scenarios.php` добавлены DOM checks для create/edit форм бота и пользователя.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/BotAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ProfileAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l public/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/http_setup_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task02-import-export-controller.md`
- `task03-bot-api-route-registry.md`
- `task04-chat-all-attachments-ui.md`
- `task05-group-chat-admin-ui.md`
- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task02-import-export-controller.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

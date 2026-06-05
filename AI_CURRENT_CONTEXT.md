# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task12-bot-api-surface-catalog.md`.

Что сделано:

- Добавлен machine-readable каталог `docs/bot-api-surface.json` для локально поддерживаемой Bot API surface.
- Каталог описывает method name, HTTP methods, required/optional params, content types, media upload support, limitations и test status.
- Добавлен focused test `tests/bot_api_surface_catalog_test.php`, который проверяет структуру каталога, ключевые методы и соответствие route names в `BotApiController`.
- `README.md`, `docs/limitations.md`, `docs/technical-spec.md`, `ROADMAP.md` и `AI_PROJECT_MAP.md` теперь ссылаются на каталог.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l tests/bot_api_surface_catalog_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_surface_catalog_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -r "json_decode(file_get_contents('docs/bot-api-surface.json'), true, 512, JSON_THROW_ON_ERROR); echo 'OK: catalog JSON valid'.PHP_EOL;"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task13.md` — новый untracked task-файл; следующая задача по алфавиту.

Следующий шаг: взять `.aitasks/task13.md`, обновить `AI_WORK_PLAN.md` и реализовать ее отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task11-http-log-inspector.md`.

Что сделано:

- `/request-inspector` получил фильтры по HTTP status и `ok=false`.
- Bot API HTTP log events теперь включают masked curl-like command, pretty JSON request/response body и признак `response_ok`.
- Webhook attempts в request inspector фильтруются по status и error-состоянию, а update id ведет ссылками в `/updates` и `/delivery-attempts`.
- Маскирование bot token и webhook secret сохранено для Bot API и webhook секций.
- Обновлены `README.md`, `docs/technical-spec.md` и `ROADMAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/HttpLogRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/InspectorController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/request-inspector/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/webhook_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task12-bot-api-surface-catalog.md`
- `task13.md` — новый untracked task-файл; обработать по алфавиту после task12, если он останется в очереди.

Следующий шаг: взять `.aitasks/task12-bot-api-surface-catalog.md`, обновить `AI_WORK_PLAN.md` и реализовать ее отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `getUpdates.timeout` в single-process PHP server ограничивается `LONG_POLLING_MAX_TIMEOUT_SECONDS`.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.

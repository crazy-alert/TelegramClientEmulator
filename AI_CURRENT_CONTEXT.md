# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task06-webhook-retry-backoff.md`.

Что сделано:

- Добавлен ручной batch retry failed webhook updates через `POST /updates/retry-failed`.
- На экране `/updates` для выбранного бота появилась форма retry с `retry_limit` и `retry_delay_ms`.
- `InspectorController` синхронно повторяет failed webhook updates выбранного бота без scheduler/worker и редиректит на `/delivery-attempts`.
- `UpdateRepository` умеет выбирать failed webhook updates выбранного бота с лимитом.
- Добавлен HTTP smoke-сценарий `tests/scenarios/webhook_retry_scenarios.php` для failed и successful batch retry.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task07-group-chat-model.md`
- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task07-group-chat-model.md`, обновить `AI_WORK_PLAN.md` и проверить текущую модель group/supergroup chat.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Webhook batch retry является локальным development helper: синхронный UI-запрос, лимит до 50 updates, задержка до 5000 мс между попытками, без production scheduler и фоновых workers.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.
- `InspectorController` отвечает за inspector/update/delivery UI routes, фильтры списков, ручной retry webhook delivery и маскирование секретов в inspector HTML-выводе.

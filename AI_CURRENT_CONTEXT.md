# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task05-split-http-scenarios.md`.

Что сделано:

- `tests/scenarios/http_scenarios.php` превращен в оркестратор HTTP smoke-фаз и оставлен подключаемым entrypoint для `tests/bot_api_test.php`.
- Большой HTTP сценарий разделен на тематические файлы: setup, import/export, Bot API core/message/surface, webhook, chat UI, Long Polling, imported dialog, group chat, media и callback error.
- Порядок выполнения smoke-фаз сохранен, чтобы не менять stateful-семантику сценариев.
- Обновлены `README.md`, `docs/adr-testing.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task06-webhook-retry-backoff.md`
- `task07-group-chat-model.md`
- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task06-webhook-retry-backoff.md`, обновить `AI_WORK_PLAN.md` и продумать модель retry/backoff для webhook delivery.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.
- `InspectorController` отвечает за inspector/update/delivery UI routes, фильтры списков, повтор webhook delivery и маскирование секретов в inspector HTML-выводе.

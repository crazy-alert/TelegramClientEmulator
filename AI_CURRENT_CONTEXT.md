# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task04-application-decomposition.md`.

Что сделано:

- Добавлен `InspectorController` для UI/admin-среза `/updates`, `/updates/clear`, `/updates/{id}/resend`, `/delivery-attempts` и `/request-inspector`.
- `Application` теперь делегирует inspector/update/delivery routes в `InspectorController` и больше не содержит их handlers и маскирование секретов inspector-вывода.
- `public/index.php` подключает новый контроллер.
- Обновлены `AI_PROJECT_MAP.md` и `docs/technical-spec.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task05-split-http-scenarios.md`
- `task06-webhook-retry-backoff.md`
- `task07-group-chat-model.md`
- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task05-split-http-scenarios.md`, обновить `AI_WORK_PLAN.md` и начать декомпозицию HTTP smoke scenarios.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.
- `InspectorController` отвечает за inspector/update/delivery UI routes, фильтры списков, повтор webhook delivery и маскирование секретов в inspector HTML-выводе.

# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task09-long-polling-timeout-model.md`.

Что сделано:

- Верхняя граница ожидания `getUpdates.timeout` вынесена из hardcoded `3` в env-настройку `LONG_POLLING_MAX_TIMEOUT_SECONDS`.
- Default для `LONG_POLLING_MAX_TIMEOUT_SECONDS` — `3`, допустимый диапазон — `0`–`30` секунд.
- `BotApiController` по-прежнему отвечает за HTTP-policy `getUpdates`: конфликт с активным webhook и короткий wait loop.
- `LongPollingService` остается сервисом queue/offset/allowed_updates без HTTP-зависимостей.
- В HTTP smoke runner выставлен `LONG_POLLING_MAX_TIMEOUT_SECONDS=1` и добавлена проверка, что пустая очередь ждет ненулевой timeout, но ограничивается env-верхней границей.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md` и `ROADMAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotApiController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/long_polling_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/long_polling_service_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`
- `task13.md` — новый untracked task-файл, не относится к task09; обработать по алфавиту после task12, если он останется в очереди.

Следующий шаг: взять `.aitasks/task10-command-scopes-language.md`, обновить `AI_WORK_PLAN.md` и реализовать ее отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `getUpdates.timeout` в текущей single-process PHP server model не должен блокировать UI надолго; верхняя граница управляется `LONG_POLLING_MAX_TIMEOUT_SECONDS`.
- Import/export v1 для bots/profiles остается стабильным; fixture pack v2 использует top-level массивы и не встраивает бинарные media.
- Group chat модель нормализована через `chats/chat_members`, но `profiles.chat_id/chat_type` остаются совместимой поверхностью UI и import/export.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.

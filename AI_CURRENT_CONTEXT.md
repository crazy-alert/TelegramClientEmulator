# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task01-long-polling-service.md`.

Что сделано:

- Добавлен `App\LongPollingService`.
- Сервис выбирает pending updates для `getUpdates`, подтверждает offset, обрабатывает negative offset, применяет limit и фильтрует `allowed_updates`.
- `BotApiController` оставлен владельцем HTTP-политики `getUpdates`: поиск бота, конфликт с активным webhook, parsing параметров, цикл короткого ожидания и JSON response.
- Добавлен focused test `tests/long_polling_service_test.php`.
- Обновлены `README.md`, `docs/technical-spec.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/long_polling_service_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_params_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_payload_factory_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task02-typed-media-multipart-upload.md`
- `task03-media-preview-download-links.md`
- `task04-application-decomposition.md`
- `task05-split-http-scenarios.md`
- `task06-webhook-retry-backoff.md`
- `task07-group-chat-model.md`
- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task02-typed-media-multipart-upload.md`, обновить `AI_WORK_PLAN.md` и расширить multipart upload на typed media методы.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.

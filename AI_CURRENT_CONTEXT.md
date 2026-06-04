# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task02-bot-api-payload-factory.md`.

Что сделано:

- Добавлен `App\BotApiPayloadFactory`.
- Фабрика собирает Telegram-like `Message`, `Chat`, `photo`, `document` и typed media payload для локального Bot API.
- `BotApiController` больше не владеет низкоуровневой сборкой `Message`/`Chat`/media payload; контроллер оставлен владельцем маршрутов, валидации, HTTP-ошибок и записи сообщений.
- Добавлен focused test `tests/bot_api_payload_factory_test.php`.
- Обновлены `README.md`, `docs/technical-spec.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_payload_factory_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_params_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Очередь `.aitasks/` после task02 была пустой, затем `AI_PROPOSALS.md` обновлен и разложен в новые task-файлы:

- `task01-long-polling-service.md`
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

Следующий шаг: взять `task01-long-polling-service.md`, обновить `AI_WORK_PLAN.md` и вынести logic `getUpdates` в сервис с focused tests.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.

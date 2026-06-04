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

Очередь `.aitasks/` должна быть пустой после коммита task02.

Следующий шаг после пустой очереди: обновить `AI_PROPOSALS.md` по текущему состоянию проекта и, если нужны новые работы, снова разложить их в `.aitasks/`.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.

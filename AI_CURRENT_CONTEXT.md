# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task01-bot-api-params-helper.md`.

Что сделано:

- Добавлен `App\BotApiParams`.
- Helper нормализует объединение query/body параметров, `int`, `float`, boolean-like значения, `allowed_updates`, commands и poll options.
- `BotApiController` использует `BotApiParams` для чистого parsing; `requireParam` оставлен в контроллере, потому что он формирует HTTP 400.
- Добавлен focused test `tests/bot_api_params_test.php`.
- Обновлены `README.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_params_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task02-bot-api-payload-factory.md`

Следующий шаг: взять `task02-bot-api-payload-factory.md`, обновить `AI_WORK_PLAN.md` и вынести Telegram-like payload builders из `BotApiController`.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` не отвечает за HTTP response policy; ошибки required-параметров пока остаются в `BotApiController`.

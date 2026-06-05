# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена `.aitasks/task07-split-message-scenarios.md`.

Что сделано:

- Крупный `tests/scenarios/bot_api_message_scenarios.php` превращен в wrapper.
- Message core сценарии вынесены в `tests/scenarios/bot_api_message_core_scenarios.php`.
- Media method сценарии вынесены в `tests/scenarios/bot_api_media_method_scenarios.php`.
- Structured method сценарии вынесены в `tests/scenarios/bot_api_structured_method_scenarios.php`.
- `tests/bot_api_test.php` не менялся; общий entrypoint `runBotApiMessageScenarios()` сохранен.
- В failure messages добавлены короткие префиксы `[message core]`, `[media methods]`, `[structured methods]`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/bot_api_message_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/bot_api_message_core_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/bot_api_media_method_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/bot_api_structured_method_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшихся задач в `.aitasks/` нет.

Следующий шаг: ждать новую задачу пользователя.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- Group/supergroup membership управляется через существующие profiles, чтобы сохранить совместимость с `profiles.chat_id` и import/export.
- Development webhook retry выполняется синхронно в текущем HTTP-запросе и не является production scheduler.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

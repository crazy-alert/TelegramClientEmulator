# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена компактная навигация и вкладка `/chat`.

Что сделано:

- В `templates/layout.php` навигация стала button-like, текущая вкладка подсвечивается классом `active`.
- Видимые `h1` заголовки страниц скрыты глобально; текущий раздел теперь показывается через активную вкладку навигации.
- В `templates/chat/index.php` удален отдельный заголовок `Чат`.
- Форма выбора отправителя/бота и панель текущего диалога на `/chat` перенесены в один `details.panel.chat-context`.
- `tests/scenarios/chat_ui_scenarios.php` проверяет активную вкладку `/chat` и единый spoiler для контекста.
- `tests/scenarios/webhook_scenarios.php` проверяет активную вкладку `/updates` вместо видимого h1.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l templates/layout.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/chat/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/chat_ui_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/webhook_scenarios.php` — успешно.
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

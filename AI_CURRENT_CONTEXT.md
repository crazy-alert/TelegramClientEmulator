# Текущий контекст проекта

## Последнее обновление

2026-06-06: обновлены health-доступ на панели и пояснение Inspector.

Что сделано:

- Кнопка `Health` удалена из общей навигации в `templates/layout.php`.
- Raw endpoint `/health` оставлен доступным напрямую.
- На `/` добавлен блок `Health` со статусной сводкой и ссылкой на `/health`.
- На `/request-inspector` добавлен поясняющий блок: пустые секции нормальны, если еще нет Bot API HTTP logs или webhook delivery attempts.
- Smoke-тесты обновлены: панель проверяет ссылку `/health` и отсутствие nav-кнопки `Health`; Inspector проверяет поясняющий блок.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l templates/layout.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/dashboard.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/request-inspector/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/http_setup_scenarios.php` — успешно.
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

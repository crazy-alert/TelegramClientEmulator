# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена `.aitasks/task06-webhook-dev-retry-backoff.md`.

Что сделано:

- Добавлены настройки development webhook retry на панели `/`: max attempts и delay.
- `WEBHOOK_RETRY_MAX_ATTEMPTS` и `WEBHOOK_RETRY_DELAY_MS` добавлены в Docker/env документацию и compose defaults.
- `WebhookDeliveryService::deliver()` возвращает результат попытки, а `deliverWithDevelopmentRetry()` выполняет короткие синхронные retry для автоматической webhook-доставки.
- Manual resend и batch retry failed updates остались одноразовыми helper-действиями.
- Каждая попытка сохраняется в `delivery_attempts`; промежуточные ошибки не скрываются.
- `tests/scenarios/webhook_retry_scenarios.php` проверяет настройки, retry sequence `500,500,202`, delivery attempts и сохранение ручного retry.
- `README.md`, `docs/limitations.md`, `docs/technical-spec.md` и `ROADMAP.md` обновлены с ограничением: это не production scheduler.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/WebhookDeliveryService.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/dashboard.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/webhook_retry_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/support/test_helpers.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task07-split-message-scenarios.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- Group/supergroup membership управляется через существующие profiles, чтобы сохранить совместимость с `profiles.chat_id` и import/export.
- Development webhook retry выполняется синхронно в текущем HTTP-запросе и не является production scheduler.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

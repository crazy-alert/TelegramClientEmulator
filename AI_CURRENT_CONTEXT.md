# Текущий контекст проекта

## Последнее обновление

2026-06-05: после пустой очереди обновлены `AI_PROPOSALS.md` и `.aitasks/`.

Что сделано:

- Убраны устаревшие предложения из `AI_PROPOSALS.md`, которые уже были реализованы предыдущими задачами.
- Добавлены актуальные предложения: admin controllers, import/export controller, Bot API route registry, полный UI вложений, group chat admin UI, dev retry/backoff, split message scenarios.
- Созданы task-файлы `.aitasks/task01-admin-controllers.md` ... `.aitasks/task07-split-message-scenarios.md`.

Проверки:

- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task01-admin-controllers.md`
- `task02-import-export-controller.md`
- `task03-bot-api-route-registry.md`
- `task04-chat-all-attachments-ui.md`
- `task05-group-chat-admin-ui.md`
- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task01-admin-controllers.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

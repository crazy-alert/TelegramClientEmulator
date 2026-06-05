# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task13.md`.

Что сделано:

- На вкладке `Чат` блок `Последний Update (inspector)` свернут в `<details class="panel chat-update-inspector">`.
- Содержимое inspector, `Raw payload (JSON)`, `Webhook delivery`, response body и кнопка resend сохранены без изменения поведения.
- В `templates/layout.php` добавлены компактные стили для `chat-update-inspector`.
- В `tests/scenarios/chat_ui_scenarios.php` добавлены string/DOM assertions, что последний update inspector находится внутри `details/summary`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l templates/chat/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/layout.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/chat_ui_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- Нет активных task-файлов кроме `.gitkeep`.

Следующий шаг: если очередь пуста, проанализировать код и при необходимости обновить `AI_PROPOSALS.md`/создать новые `.aitasks` по правилам пользователя.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

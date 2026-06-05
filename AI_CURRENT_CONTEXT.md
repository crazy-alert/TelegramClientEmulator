# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task04-chat-all-attachments-ui.md`.

Что сделано:

- `ChatController::messageDataFromPost()` расширен для пользовательских `message_type`: `video`, `animation`, `audio`, `voice`, `video_note`, `sticker`, `poll`, `venue`, `dice`.
- `ChatController` переиспользует `BotApiPayloadFactory` для typed media payload.
- В `/chat` раскрывающийся блок `Вложения` получил компактные формы для всех новых типов.
- `tests/scenarios/chat_ui_scenarios.php` проверяет DOM-наличие новых форм и создание dice/venue/poll через `/chat/send`.
- `README.md`, `docs/limitations.md` и `docs/technical-spec.md` обновлены под полный UI attachments scope.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/chat/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/layout.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/chat_ui_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task05-group-chat-admin-ui.md`
- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task05-group-chat-admin-ui.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

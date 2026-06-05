# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена `.aitasks/task05-group-chat-admin-ui.md`.

Что сделано:

- Добавлен `GroupChatAdminController` с маршрутами `/group-chats`, `/group-chats/{chat_id}`, добавлением и удалением участников через существующие profiles.
- `ChatRepository` расширен выборкой group/supergroup чатов с количеством участников и поиском group/supergroup по `chat_id`.
- В навигацию добавлен пункт `Группы`; созданы шаблоны `templates/group-chats/index.php` и `templates/group-chats/show.php`.
- `ProfileAdminController` теперь валидирует конфликт private/channel `chat_id` с group/supergroup `chat_id`; `ProfileRepository::hasConflictingChatId()` поддерживает исключение текущего profile при редактировании.
- `tests/scenarios/group_chat_scenarios.php` покрывает список групп, экран участников, добавление/удаление участника и конфликтные private/group `chat_id` cases.
- `README.md`, `docs/limitations.md`, `docs/technical-spec.md` и `ROADMAP.md` обновлены под новый group chat admin UI scope.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l public/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/GroupChatAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ProfileAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ProfileRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/group-chats/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/group-chats/show.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/layout.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l tests/scenarios/group_chat_scenarios.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task06-webhook-dev-retry-backoff.md`
- `task07-split-message-scenarios.md`

Следующий шаг: взять `.aitasks/task06-webhook-dev-retry-backoff.md`, обновить `AI_WORK_PLAN.md` и реализовать отдельным коммитом.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Каталог `docs/bot-api-surface.json` должен описывать только реально поддерживаемую локальную surface и сверяться с route names в `BotApiController`.
- Inspector HTML не должен раскрывать raw bot token и webhook secret; copy-friendly curl тоже строится из замаскированных данных.
- Command scopes в Bot API работают exact по `scope + language_code`; UI делает отдельный fallback для текущего чата.
- Group/supergroup membership управляется через существующие profiles, чтобы сохранить совместимость с `profiles.chat_id` и import/export.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; focused catalog test проверяет документационный контракт без запуска HTTP server.

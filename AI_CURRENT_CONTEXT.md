# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена задача `.aitasks/002-group-member-roles.md` — базовые роли участников group/supergroup.

Что сделано:

- `chat_members.role` используется как базовая роль участника: `member` или `administrator`.
- `ChatRepository` получил `updateMemberRole()`.
- `ProfileRepository` подмешивает `chat_role` в profile reads.
- `GroupChatAdminController` получил POST-маршрут `/group-chats/{chat_id}/members/{profile_id}/role` с валидацией роли.
- На `/group-chats/{chat_id}` добавлена форма смены роли участника.
- `BotCommandRepository::forChatContext()` учитывает роль `administrator` для UI-подбора command scopes `chat_administrators` и `all_chat_administrators`.
- Exact Bot API `getMyCommands` не изменен: запросы по scope остаются exact.
- Тесты обновлены: `tests/chat_repository_test.php` и `tests/scenarios/group_chat_scenarios.php` проверяют смену роли и admin command scopes в UI.
- Документация обновлена: `docs/limitations.md`, `docs/technical-spec.md`, `ROADMAP.md`.
- Файл задачи `.aitasks/002-group-member-roles.md` удален перед коммитом.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ProfileRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/GroupChatAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotCommandRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Осталась задача:

- `.aitasks/003-group-service-messages.md`

Следующий шаг: обработать `.aitasks/003-group-service-messages.md` по алфавиту.

## Важные решения

- Роли ограничены `member` и `administrator`; Telegram permissions не моделируются.
- Admin role влияет только на UI-подбор команд для текущего chat context, не меняет exact Bot API semantics.
- Проект Docker-first; проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

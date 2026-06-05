# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена задача `.aitasks/001-group-title-history.md` — редактирование title group/supergroup чата.

Что сделано:

- На `/group-chats/{chat_id}` добавлена форма редактирования title group/supergroup чата.
- Добавлен POST-маршрут `/group-chats/{chat_id}/title` с валидацией непустого title до 128 символов.
- `ChatRepository` получил `updateGroupTitle()`.
- `ProfileRepository` теперь подмешивает `chat_title` в profile reads и не перезаписывает существующий ручной title при sync membership.
- `UpdateGenerator` и `BotApiPayloadFactory` используют `chat_title` для Telegram-like group/supergroup/channel payload с fallback `Chat <chat_id>`.
- Тесты обновлены: `tests/chat_repository_test.php` и `tests/scenarios/group_chat_scenarios.php` проверяют редактирование title, валидацию, сохранение title после profile sync и title в group update.
- Документация обновлена: `docs/limitations.md`, `docs/technical-spec.md`, `ROADMAP.md`.
- Файл задачи `.aitasks/001-group-title-history.md` удален перед коммитом.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/ChatRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/ProfileRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/GroupChatAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/UpdateGenerator.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/BotApiPayloadFactory.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Остались задачи:

- `.aitasks/002-group-member-roles.md`
- `.aitasks/003-group-service-messages.md`

Следующий шаг: обработать `.aitasks/002-group-member-roles.md` по алфавиту.

## Важные решения

- История group/supergroup чата остается привязанной к `bot_id + chat_id`; отдельная очистка истории группы не добавлялась в задаче title.
- Ручной title в `chats.title` не должен перезаписываться обновлением profiles.
- Проект Docker-first; проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

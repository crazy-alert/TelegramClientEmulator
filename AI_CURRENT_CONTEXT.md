# Текущий контекст проекта

## Последнее обновление

2026-06-06: выполнена задача `.aitasks/003-group-service-messages.md` — локальные service messages для group/supergroup.

Что сделано:

- Добавлена миграция `migrations/005_service_message_direction.sql`: `messages.direction` теперь допускает `service`.
- `MessageRepository` получил `createService()` и строит Telegram-like raw payload для service-сообщений без создания Bot API update.
- `GroupChatAdminController` создает service messages при изменении title, добавлении участника и удалении участника через group UI.
- Service messages пишутся в историю каждого настроенного бота для этого group/supergroup chat, потому group history остается доступной по `bot_id + chat_id`.
- `templates/chat/index.php` отображает direction `service` как `Событие` отдельным визуальным стилем.
- Тесты обновлены: `tests/scenarios/group_chat_scenarios.php` проверяет видимость service messages и отсутствие Bot API updates от них.
- Документация обновлена: `docs/limitations.md`, `docs/technical-spec.md`, `ROADMAP.md`.
- Файл задачи `.aitasks/003-group-service-messages.md` удален перед коммитом.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/MessageRepository.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/GroupChatAdminController.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/chat/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Очередь `.aitasks/` пуста, остался только `.gitkeep`.

Следующий шаг: после коммита выполнить push всех коммитов очереди.

## Важные решения

- Service messages являются локальной историей UI, не Bot API updates.
- Raw payload service messages использует Telegram-like поля `new_chat_title`, `new_chat_members`, `left_chat_member`.
- Проект Docker-first; проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

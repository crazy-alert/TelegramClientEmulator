# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task07-group-chat-model.md`.

Что сделано:

- Добавлена миграция `003_chats_and_members.sql` с таблицами `chats` и `chat_members`, включая backfill из `profiles`.
- Добавлен `ChatRepository` для read-only доступа к нормализованным chats/members.
- `ProfileRepository` синхронизирует `chats/chat_members` при create/update/delete профилей.
- `profiles.chat_id/chat_type` сохранены как backward-compatible поверхность UI и import/export.
- Добавлен focused test `tests/chat_repository_test.php`.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/chat_repository_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task08-fixture-packs-import-export.md`, обновить `AI_WORK_PLAN.md` и проверить текущий import/export формат.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Group chat модель теперь нормализована через `chats/chat_members`, но `profiles.chat_id/chat_type` остаются совместимым вводом и export/import v1.
- Webhook batch retry является локальным development helper: синхронный UI-запрос, лимит до 50 updates, задержка до 5000 мс между попытками, без production scheduler и фоновых workers.
- `tests/bot_api_test.php` остается главным HTTP smoke entrypoint; `tests/scenarios/http_scenarios.php` только оркестрирует тематические фазы.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.
- `InspectorController` отвечает за inspector/update/delivery UI routes, фильтры списков, ручной retry webhook delivery и маскирование секретов в inspector HTML-выводе.

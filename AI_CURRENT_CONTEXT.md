# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task07-message-type-renderer-refactor.md`.

Что сделано:

- Добавлен `App\MessageRenderer`.
- `MessageRenderer` нормализует raw payload или update envelope в единые UI-блоки: `title`, `source`, `lines`, `items`.
- `templates/chat/index.php` больше не содержит отдельные ветки rendering для каждого типа payload; он выводит общий список блоков.
- Сохранены UI-проверки через HTTP smoke runner.
- Добавлен focused test `tests/message_renderer_test.php`.
- Обновлены `README.md`, `docs/technical-spec.md`, `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

## Ближайшая очередь

`.aitasks/` пуста после завершения task07.

Следующий шаг по правилу пользователя: когда задачи закончатся, проанализировать код и сделать предложения по модернизации в `AI_PROPOSALS.md`.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- `enable_post_data_reading=Off`, поэтому multipart body парсится вручную из `php://input`.
- File download реализован только для локальных `local-media:<sha256>` файлов, сохраненных эмулятором.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

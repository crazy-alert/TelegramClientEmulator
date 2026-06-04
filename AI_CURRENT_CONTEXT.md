# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task06-getfile-and-media-download.md`.

Что сделано:

- `GET|POST /bot<TOKEN>/getFile` возвращает Telegram-like `File` для локально сохраненных media.
- `GET /file/bot<TOKEN>/<file_path>` отдает байты файла из `MEDIA_DIR`.
- File endpoint проверяет bot token и не отдает неизвестные файлы.
- `MediaStorage` умеет искать `local-media:<sha256>`, возвращать `file_path`, безопасно разрешать путь и определять content-type.
- Защита от path traversal: download принимает только basename внутри `MEDIA_DIR`, без `/`, `\` и `..`.
- Тесты покрывают upload -> `getFile` -> download, unknown `file_id`, unknown token и path traversal.
- Документация обновлена: `README.md`, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task07-message-type-renderer-refactor.md`

Следующий шаг: взять `task07-message-type-renderer-refactor.md`, обновить `AI_WORK_PLAN.md` и выполнить refactor renderer.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- `enable_post_data_reading=Off`, поэтому multipart body парсится вручную из `php://input`.
- File download реализован только для локальных `local-media:<sha256>` файлов, сохраненных эмулятором.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

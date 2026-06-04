# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task05-file-upload-and-local-media-storage.md`.

Что сделано:

- Добавлено локальное media-хранилище `App\MediaStorage`.
- `MEDIA_DIR` задает путь хранения, по умолчанию `<DATA_DIR>/media`.
- `MEDIA_MAX_BYTES` задает лимит одного upload-файла, по умолчанию `10485760`.
- Multipart parser теперь возвращает текстовые поля и файловые части в internal-ключе `BotApiRequestParser::FILES_KEY`.
- `sendPhoto` и `sendDocument` принимают multipart upload в каноничных полях `photo` и `document`.
- Строковые URL/file_id для `sendPhoto` и `sendDocument` сохранены без изменения.
- UI `/chat` в блоке `Вложения` умеет прикреплять локальный файл для photo/document; файл имеет приоритет над строковым URL/file_id.
- Для upload возвращаются стабильные `file_id` вида `local-media:<sha256>` и `file_unique_id` от содержимого.
- Имена файлов очищаются от path traversal и небезопасных символов.
- Документация обновлена: `README.md`, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md`, `.env.example`, `docker-compose.yml`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task06-getfile-and-media-download.md`
- `task07-message-type-renderer-refactor.md`

Следующий шаг: взять `task06-getfile-and-media-download.md`, обновить `AI_WORK_PLAN.md` и реализовать `getFile`/локальную отдачу media.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- `enable_post_data_reading=Off`, поэтому multipart body парсится вручную из `php://input`.
- `getFile` и download URL еще не реализованы; это следующая задача.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.

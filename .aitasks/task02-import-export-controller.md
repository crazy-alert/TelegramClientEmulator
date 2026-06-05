# Задача 02: вынести import/export и fixture pack logic из `Application`

Цель: отделить import/export routes, JSON validation и fixture pack logic от общего composition root.

Минимальный результат:

- добавить `ImportExportController` для `/import-export`, `/export/*`, `/import/*`;
- при необходимости добавить отдельный helper/service для validation и normalization fixture pack;
- сохранить JSON envelope v1, текущие ограничения и HTTP-поведение;
- добавить tests на конфликт `chat_id`, bot command sections и отказ от бинарных media внутри JSON;
- обновить `AI_PROJECT_MAP.md` и профильную документацию, если меняется архитектурное описание.

Проверки:

- syntax check новых/измененных PHP-файлов;
- focused import/export tests, если есть отдельный entrypoint;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

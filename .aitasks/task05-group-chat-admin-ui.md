# Задача 05: добавить экран управления групповыми чатами и участниками

Цель: сделать group/supergroup chats явной частью UI, а не только побочным эффектом profiles/import.

Минимальный результат:

- добавить UI списка group/supergroup chats с title, `chat_id`, type;
- добавить управление участниками через существующие profiles;
- сохранить текущую совместимость profiles с `chat_id`;
- добавить validation на конфликт private/group `chat_id`;
- покрыть happy path и конфликтные cases тестами;
- обновить README/docs/ROADMAP при изменении продуктового scope.

Проверки:

- syntax check новых/измененных PHP-файлов;
- focused tests для group chat UI/validation;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

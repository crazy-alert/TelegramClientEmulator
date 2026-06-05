# Задача 01: вынести admin UI ботов и пользователей из `Application`

Цель: уменьшить ответственность `src/Application.php`, вынеся маршруты и обработчики `/bots` и `/profiles` в отдельные контроллеры.

Минимальный результат:

- добавить `BotAdminController` для `/bots`, `/bots/new`, `/bots/{id}/edit`, create/update;
- добавить `ProfileAdminController` для `/profiles`, `/profiles/new`, `/profiles/{id}/edit`, create/update;
- сохранить текущие URL, HTTP status, redirects, шаблоны и тексты ошибок;
- оставить `Application` composition root и dispatcher без бизнес-логики этих форм;
- добавить или обновить focused HTTP/DOM checks для страниц forms;
- обновить `AI_PROJECT_MAP.md` и документацию, если меняется архитектурное описание.

Проверки:

- syntax check новых/измененных PHP-файлов;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

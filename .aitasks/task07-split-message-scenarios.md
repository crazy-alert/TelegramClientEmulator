# Задача 07: дробить крупные message scenarios

Цель: сделать `tests/scenarios/bot_api_message_scenarios.php` проще для поддержки.

Минимальный результат:

- разделить сценарии на message core, media methods и structured methods;
- не менять общий entrypoint `tests/bot_api_test.php`;
- сохранить порядок setup и независимость runtime;
- добавить короткие названия сценариев в failure messages, где это помогает;
- не менять продуктовый код без необходимости.

Проверки:

- syntax check новых/измененных test-файлов;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

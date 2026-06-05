# Задача 03: сделать registry маршрутов Bot API

Цель: заменить длинную цепочку `preg_match` в `BotApiController::handle()` явной таблицей route definitions.

Минимальный результат:

- ввести registry с method name, HTTP verbs и handler;
- сохранить каноничный URL `/bot<TOKEN>/<METHOD>` и текущие ошибки unsupported methods;
- не добавлять aliases, alternative paths или неканоничную семантику;
- обновить `tests/bot_api_surface_catalog_test.php`, чтобы каталог сверялся с registry;
- проверить, что `GET|POST` и `POST`-only методы ведут себя как раньше.

Проверки:

- syntax check `src/BotApiController.php`;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_surface_catalog_test.php`;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

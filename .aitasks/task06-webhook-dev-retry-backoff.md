# Задача 06: улучшить development webhook retry/backoff

Цель: добавить локальную настраиваемую модель коротких retry для webhook delivery без production scheduler.

Минимальный результат:

- добавить настройки max attempts и delay для development retry;
- выполнять короткие синхронные retry только в рамках локального helper-режима;
- показывать попытки в inspector/delivery attempts без скрытия ошибок;
- явно документировать ограничение: это не production scheduler;
- сохранить ручной retry failed updates.

Проверки:

- syntax check измененных PHP-файлов;
- focused webhook retry scenarios;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

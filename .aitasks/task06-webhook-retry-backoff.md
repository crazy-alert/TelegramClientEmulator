# Задача 06: Webhook retry/backoff

## Цель

Добавить development-friendly retry/backoff для webhook delivery без production scheduler.

## Ожидаемый результат

- Есть настройка количества retry и задержки или manual batch retry для failed updates.
- Попытки логируются отдельно и видны в существующих delivery attempts.
- Поведение явно документировано как локальный development helper.
- Добавлены tests для failed/success retry.

## Ограничения

- Не добавлять фоновые workers без отдельного решения по runtime.
- Не строить production scheduler.

# Ручной resend failed webhook delivery

Источник: `ROADMAP.md`, Этап 4.

## Цель

Добавить возможность вручную повторить failed webhook delivery для выбранного update.

## Ожидаемый результат

- В интерфейсе доступна кнопка повторной отправки для failed webhook delivery.
- Повторная отправка использует сохраненный update payload и текущие webhook-настройки бота.
- Новая попытка сохраняется в `delivery_attempts`.
- Состояние update обновляется на `delivered` или `failed`.
- Добавлены тесты на успешный/ошибочный resend там, где это практично.


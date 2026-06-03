# Улучшить модель reply markup

Источник: `AI_PROPOSALS.md`.

## Цель

Сделать работу с `reply_markup` менее хрупкой.

## Ожидаемый результат

- Добавлен helper/value object для чтения/записи reply markup или отдельное поле в `messages`.
- UI и Bot API используют общий helper.
- Тесты покрывают inline/reply keyboard после изменения.


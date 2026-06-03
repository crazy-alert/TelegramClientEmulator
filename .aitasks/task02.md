# Экран delivery attempts

Источник: `ROADMAP.md`, Этап 4.

## Цель

Добавить отдельный экран для просмотра webhook delivery attempts.

## Ожидаемый результат

- Маршрут `/delivery-attempts` показывает список попыток доставки.
- Есть фильтр по боту и/или update.
- Видны webhook URL, HTTP status, duration, error, request body, response body.
- Из списка можно перейти к связанному update или чату.
- Добавлены тесты или smoke-проверка HTML.


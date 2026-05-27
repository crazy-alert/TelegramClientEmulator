# Активный план работы

## Текущая задача

Добавить Bot API метод `GET|POST /bot<TOKEN>/getWebhookInfo`.

## Чеклист

- [completed] Проверить текущие поля webhook и очереди updates.
- [completed] Добавить маршрут и Telegram-like ответ `getWebhookInfo`.
- [completed] Обновить README и текущий контекст.
- [completed] Прогнать PHP lint и HTTP-проверку `getWebhookInfo` в Docker.
- [completed] Проверить diff/status, сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- История webhook delivery attempts пока не реализована, поэтому `last_error_*` не возвращаем до появления реальной доставки.

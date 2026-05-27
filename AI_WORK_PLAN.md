# Активный план работы

## Текущая задача

Реализовать webhook delivery loop: отправлять созданные updates на `webhook_url`, сохранять попытки доставки и показывать результат в инспекторе.

## Чеклист

- [completed] Проверить схему `delivery_attempts` и текущий путь создания updates.
- [completed] Добавить репозиторий попыток доставки webhook.
- [completed] Добавить отправку JSON update на webhook URL с `Content-Type` и optional secret token.
- [completed] Обновить состояние update (`delivered`/`failed`) и inspector в UI.
- [completed] Обновить README/контекст при необходимости.
- [completed] Прогнать PHP lint и HTTP-проверку webhook delivery в Docker.
- [completed] Проверить diff/status, сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- Внешних опасных прав не требуется; webhook delivery делает обычный HTTP POST на URL, настроенный пользователем для локальной разработки.
- Retry и ручной resend оставляем на следующий шаг: в этом этапе одна попытка на созданный update.

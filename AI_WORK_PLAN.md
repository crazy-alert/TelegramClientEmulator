# Активный план работы

## Текущая задача

Реализовать следующий этап: Long Polling `GET|POST /bot<TOKEN>/getUpdates` для локального Bot API.

## Чеклист

- [completed] Проверить текущую модель updates и параметры Bot API, которые нужно поддержать.
- [completed] Добавить методы очереди для подтверждения offset, выдачи pending updates и подсчета очереди.
- [completed] Реализовать маршрут `getUpdates` с `offset`, `limit`, `timeout`, `allowed_updates` и конфликтом при активном webhook.
- [completed] Обновить UI/документацию минимально там, где нужно показать состояние Long Polling.
- [completed] Прогнать PHP lint и HTTP-проверку Long Polling в Docker.
- [completed] Обновить `AI_CURRENT_CONTEXT.md`, проверить diff/status, сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- Внешние сервисы и опасные права не требуются: работа локальная, в PHP/SQLite.
- `timeout` в MVP делаем коротким ожиданием с верхней границей, чтобы не подвесить single-process встроенный PHP server.

# Активный план работы

## Текущая задача

Реализовать `POST /bot<TOKEN>/sendMessage`: принимать ответ бота, сохранять его в историю диалога и возвращать Telegram-like `Message`.

## Чеклист

- [completed] Проверить текущие репозитории сообщений/профилей и нужные параметры `sendMessage`.
- [completed] Добавить поиск профиля по `bot_id` и `chat_id`.
- [completed] Добавить сохранение сообщения бота с возвратом созданной записи.
- [completed] Реализовать маршрут `sendMessage` с JSON/form-urlencoded body и Telegram-like ошибками.
- [completed] Обновить README/контекст при необходимости.
- [completed] Прогнать PHP lint и HTTP-проверку `sendMessage` в Docker.
- [completed] Обновить `AI_CURRENT_CONTEXT.md`, проверить diff/status, сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- Внешние сервисы и опасные права не требуются: работа локальная, в PHP/SQLite.
- Scope MVP: текстовые сообщения без клавиатур, attachments и полного набора параметров Telegram Bot API.

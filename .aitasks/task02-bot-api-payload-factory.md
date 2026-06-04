# Задача 02: BotApiPayloadFactory

## Цель
Вынести создание Telegram-like payload (`Message`, `Chat`, media objects) из `BotApiController` в отдельный service/factory.

## Ожидаемый результат
- Добавлен `BotApiPayloadFactory` или аналогичный helper.
- `BotApiController` перестает владеть низкоуровневой сборкой `Message`/`Chat`/media payload там, где это можно сделать без изменения поведения.
- HTTP/Bot API tests продолжают проходить.
- Архитектурная документация обновлена.

## Примечания
- Не менять Bot API surface и тексты ошибок.
- Если задача получается слишком большой, сначала вынести только `Message`/`Chat`, media оставить отдельной подзадачей.

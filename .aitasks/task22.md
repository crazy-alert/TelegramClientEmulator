# Отдельный parser Bot API request parameters

Источник: `AI_PROPOSALS.md`.

## Цель

Вынести parsing JSON, form-urlencoded и multipart text fields из `Application`.

## Ожидаемый результат

- Создан `BotApiRequestParser`.
- Есть тесты на JSON, form-urlencoded, multipart, пустое тело и malformed JSON.
- Поведение существующих Bot API методов не меняется.


# Задача 01: LongPollingService

## Цель

Вынести логику `getUpdates` queue/offset/allowed_updates из `BotApiController` в отдельный сервис без изменения Bot API surface.

## Ожидаемый результат

- Добавлен `LongPollingService` или аналогичный сервис.
- Сервис отвечает за выбор pending updates, подтверждение offset, negative offset, limit и фильтрацию `allowed_updates`.
- `BotApiController` оставляет за собой проверку bot/webhook conflict и HTTP response.
- Добавлены focused tests для сервиса.
- HTTP/Bot API tests продолжают проходить.
- Архитектурная документация обновлена.

## Ограничения

- Не менять текущий лимит ожидания и тексты HTTP-ошибок.
- Не добавлять неканоничные параметры Bot API.

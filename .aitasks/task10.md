# Реализовать editMessageText

Источник: `ROADMAP.md`, Этап 7 и `AI_PROPOSALS.md`.

## Цель

Добавить метод Bot API `editMessageText` для редактирования сообщений бота.

## Ожидаемый результат

- `POST /bot<TOKEN>/editMessageText` принимает JSON и form-urlencoded.
- Поддержаны `chat_id`, `message_id`, `text`, optional `reply_markup`.
- Метод редактирует только сообщения бота.
- Возвращается Telegram-like `Message`.
- Ошибки неизвестного чата/сообщения возвращают каноничный Telegram-like 400.
- Добавлены тесты.


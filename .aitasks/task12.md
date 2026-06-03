# Базовый sendDocument

Источник: `ROADMAP.md`, Этап 7.

## Цель

Добавить минимальную поддержку `sendDocument`.

## Ожидаемый результат

- `POST /bot<TOKEN>/sendDocument` принимает `chat_id`, `document`, optional `caption`, optional `reply_markup`.
- Для первой версии допускается текстовое/URL представление document без загрузки файла.
- Сообщение отображается в чате как document placeholder с caption.
- Возвращается Telegram-like `Message` с `document`.
- Документированы ограничения файловых upload.
- Добавлены тесты.


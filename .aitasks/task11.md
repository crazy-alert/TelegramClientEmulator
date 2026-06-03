# Базовый sendPhoto

Источник: `ROADMAP.md`, Этап 7.

## Цель

Добавить минимальную поддержку `sendPhoto` для локального тестирования ботов с изображениями.

## Ожидаемый результат

- `POST /bot<TOKEN>/sendPhoto` принимает `chat_id`, `photo`, optional `caption`, optional `reply_markup`.
- Для первой версии допускается текстовое/URL представление photo без загрузки файла.
- Сообщение отображается в чате как photo placeholder с caption.
- Возвращается Telegram-like `Message` с `photo`.
- Документированы ограничения файловых upload.
- Добавлены тесты.


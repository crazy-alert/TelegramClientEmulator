# Задача 03: Audio/video/voice/sticker Bot API методы

## Цель
Добавить placeholder-поддержку исходящих media-методов без бинарного upload:

- `sendVideo`;
- `sendAnimation`;
- `sendAudio`;
- `sendVoice`;
- `sendVideoNote`;
- `sendSticker`.

## Ожидаемый результат
- Методы принимают строковый URL/file_id параметр соответствующего media-поля.
- Возвращается Telegram-like `Message` с соответствующим объектом.
- Сообщения сохраняются и отображаются в `/chat` как media placeholder.
- Добавлены тесты обязательных параметров, `chat not found`, успешного payload и UI rendering.
- Документация и ограничения обновлены.

## Примечания
- Multipart upload и `getFile` не входят в эту задачу.

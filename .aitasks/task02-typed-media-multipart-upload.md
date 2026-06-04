# Задача 02: Multipart upload для typed media

## Цель

Расширить multipart upload с `sendPhoto`/`sendDocument` на `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote` и `sendSticker`.

## Ожидаемый результат

- Методы принимают multipart file parts в каноничных media-полях.
- Используется существующий `MediaStorage`.
- Telegram-like media objects возвращают доступные `file_size`, `mime_type`, `file_name`.
- Добавлены HTTP tests для success, required param, unknown chat и `getFile`.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md` и `ROADMAP.md`.

## Ограничения

- Не менять поддержку строковых URL/file_id.
- Не добавлять альтернативные aliases media-полей.

# Задача 11: Улучшение inspector HTTP-логов

## Цель

Сделать request inspector удобнее для диагностики Bot API и webhook проблем.

## Ожидаемый результат

- Добавлены фильтры по HTTP status и `ok=false`.
- Добавлен curl-like request view или copy-friendly block.
- JSON body можно читать в pretty view/tree.
- По возможности есть связь с message/update context.
- Добавлены UI/DOM tests.

## Ограничения

- Секреты token и webhook secret должны оставаться замаскированы.

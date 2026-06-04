# Задача 08: Fixture packs import/export

## Цель

Расширить import/export от bots/profiles к повторяемым fixture packs для тестовых сценариев.

## Ожидаемый результат

- Поддержан optional export/import `bot_commands`.
- Подготовлена структура для optional groups/chats, если такая модель уже есть.
- Продумана стратегия для messages/updates и media manifest.
- Добавлены tests round-trip и конфликтов.
- Документация обновлена.

## Ограничения

- Не помещать бинарные media-файлы внутрь JSON.
- Не ломать текущий формат bots/profiles import/export.

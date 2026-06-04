# Задача 10: Command scopes и language-specific commands

## Цель

Поддержать `scope` и `language_code` для Bot API commands.

## Ожидаемый результат

- `setMyCommands` сохраняет команды с raw `scope` и `language_code`.
- `getMyCommands` возвращает подходящий набор по переданным параметрам.
- UI выбирает релевантный набор команд для текущего chat/profile.
- Добавлены tests для default, scope и language behavior.
- Документация ограничений обновлена.

## Ограничения

- Не добавлять неканоничные shortcuts.
- Сначала решить правила выбора команд в UI.

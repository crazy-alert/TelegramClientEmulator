# Задача 12: Machine-readable Bot API surface catalog

## Цель

Добавить простой JSON/YAML каталог поддерживаемой локальной Bot API surface.

## Ожидаемый результат

- Каталог содержит method name, HTTP methods, required/optional params, supported content types, media upload support, known limitations и тестовый статус.
- Документация ссылается на каталог.
- По возможности tests сверяют каталог с маршрутизацией или limitations.

## Ограничения

- Каталог должен описывать только реально поддержанную локальную surface.
- Не использовать его как повод добавить неканоничные методы или aliases.

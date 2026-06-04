# Задача 09: Long Polling timeout model

## Цель

Уточнить модель `getUpdates.timeout` после выделения Long Polling logic.

## Ожидаемый результат

- Документировано текущее ограничение single-process PHP server.
- При необходимости добавлена настройка верхней границы timeout в UI/env.
- Добавлены tests для timeout policy.

## Ограничения

- Не ухудшать отзывчивость локального UI.
- Не менять runtime/server mode без отдельного решения.

# Активный план работы

## Текущая задача

Вернуть каноническое поведение Telegram Bot API routes: только `/bot<TOKEN>/<METHOD>`, без совместимой формы `/bot/<TOKEN>/<METHOD>`.

## Чеклист

- [completed] Убрать `/bot/<TOKEN>/...` из маршрутизации.
- [completed] Обновить README, technical spec и текущий контекст.
- [completed] Проверить канонический маршрут и отклонение `/bot/<TOKEN>/...`.
- [completed] Сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- Настоящий Telegram Bot API использует форму `/bot<TOKEN>/<METHOD>`.

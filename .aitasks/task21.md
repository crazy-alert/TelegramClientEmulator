# Декомпозиция Application на контроллеры и сервисы

Источник: `AI_PROPOSALS.md`.

## Цель

Разделить `src/Application.php` на более узкие компоненты без изменения поведения.

## Ожидаемый результат

- Bot API методы вынесены в отдельный контроллер/сервис.
- Chat UI handlers вынесены отдельно.
- Webhook delivery вынесена в service.
- `Application` остается router/composition root.
- Существующие тесты проходят.


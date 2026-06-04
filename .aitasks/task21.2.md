# Вынести Chat UI handlers

Источник: `.aitasks/task21.md`.

## Цель

Продолжить декомпозицию `src/Application.php` и вынести Chat UI handlers в отдельный контроллер или сервис без изменения поведения.

## Ожидаемый результат

- Логика `/chat`, `/chat/fragment`, `/chat/send`, `/chat/callback`, `/chat/clear` вынесена из `Application` в отдельный компонент.
- `Application` остается router/composition root и делегирует chat routes.
- HTML, redirects, HTMX-фрагмент, keyboards, resend controls и group chat behavior не меняются.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` проходит.
- PHP lint проходит.

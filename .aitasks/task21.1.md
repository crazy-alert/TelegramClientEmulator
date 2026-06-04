# Вынести Bot API handlers

Источник: `.aitasks/task21.md`.

## Цель

Продолжить декомпозицию `src/Application.php` и вынести Bot API request parsing/handlers в отдельный контроллер или сервис без изменения поведения.

## Ожидаемый результат

- Bot API методы `getMe`, `getUpdates`, `sendMessage`, `sendPhoto`, `sendDocument`, `editMessageText`, webhook commands, bot commands и `answerCallbackQuery` находятся в отдельном компоненте или наборе handler-классов.
- `Application` остается router/composition root и делегирует Bot API requests.
- Telegram-like статусы, payload и ошибки не меняются.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` проходит.
- PHP lint проходит.

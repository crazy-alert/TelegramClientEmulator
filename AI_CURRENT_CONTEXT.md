# Текущий контекст проекта

## Состояние

Проект: локальный эмулятор Telegram Bot API для разработки и тестирования ботов.

Реализовано:

- Docker runtime на PHP 8.3 CLI Alpine.
- Bind mount проекта в `/app`.
- Подключение к внешней Docker-сети через `APP_BACKEND_NETWORK`.
- Health endpoint `/health`.
- SQLite bootstrap и миграция `001_initial_schema.sql`.
- CRUD ботов и профилей через server-rendered PHP templates.
- **Этап 2 завершён**: экран чата (`/chat`), отправка сообщений, генерация Telegram-like `Update`, inspector raw payload.
- Переключатели активного профиля и бота в шапке (cookie `active_profile_id` / `active_bot_id`).

## Важные решения

- Основной язык: PHP (стиль K&R для фигурных скобок).
- Интерактивность интерфейса: HTMX (пока не используется, заготовка на Этап 6).
- Хранение: SQLite в `data/telegram_emulator.sqlite`.
- Активный профиль/бот: cookie (MVP), на Этапе 6 — HTMX + SQLite settings.
- Проект не привязан к одному bot token.
- Обязательны оба режима получения updates: webhook и Long Polling.

## Новые классы (Этап 2)

| Класс | Назначение |
|---|---|
| `src/MessageRepository.php` | CRUD сообщений, выборка диалога |
| `src/UpdateRepository.php` | Сохранение/выборка updates, заготовка для Long Polling |
| `src/UpdateGenerator.php` | Генерация Telegram-like Update payload |

## Проверки

- PHP lint: все 11 файлов без синтаксических ошибок (проверено 2026-05-26).
- Docker: `docker compose up` поднимает приложение.

## Ближайший следующий этап

Этап 3: Long Polling — `GET|POST /bot{token}/getUpdates`, поддержка offset/limit/timeout, подтверждение updates.

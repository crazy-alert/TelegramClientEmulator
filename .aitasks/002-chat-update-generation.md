# Этап 2: базовый чат и генерация Telegram-like Update

## Ожидаемый результат

Пользователь выбирает активный профиль и бота через переключатели в шапке, открывает экран чата, вводит текстовое сообщение, отправляет его. Система сохраняет сообщение, генерирует Telegram-like `Update`, сохраняет его и показывает в интерфейсе историю диалога и raw payload последнего update.

## Критерий готовности

Сообщение из UI сохраняется в истории, а raw `Update` соответствует Telegram Bot API shape. Активный профиль и бот выбираются через переключатели в header.

## Требования к расширяемости

- Генерация Update вынесена в отдельный класс `UpdateGenerator` — легко добавлять новые типы updates (callback_query, edited_message) через отдельные методы.
- Каждая доменная сущность имеет свой Repository: `MessageRepository`, `UpdateRepository`.
- Маршруты чата изолированы от будущих `/bot{token}/*` маршрутов.
- Шаблоны используют компонентный подход, готовы к HTMX-обновлениям на Этапе 6.
- Выбор профиля/бота через cookie — на Этапе 6 легко заменить на HTMX + SQLite.

## Затронутые слои

- **Persistence**: таблицы `messages`, `updates` (уже в схеме `001_initial_schema.sql`)
- **Domain**: `MessageRepository`, `UpdateRepository`, `UpdateGenerator`
- **Application**: `Application.php` — `activeProfile()`, `activeBot()`, маршруты `/chat`, `/chat/send`, `/select-profile`, `/select-bot`
- **Presentation**: `templates/chat/index.php`, переключатели в `layout.php`
- **Bootstrap**: `public/index.php` — require новых классов

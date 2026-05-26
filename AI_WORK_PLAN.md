# Активный план работы

## Текущая задача

**Этап 2: базовый чат и генерация Telegram-like Update** (см. `.aitasks/002-chat-update-generation.md`)

## Архитектурные решения (расширяемость)

### Новые классы

| Класс | Назначение | Расширяемость |
|---|---|---|
| `src/MessageRepository.php` | CRUD для таблицы `messages` | Готов к добавлению новых типов сообщений (photo, document) |
| `src/UpdateRepository.php` | Сохранение и выборка `updates` | Закладывает основу для Long Polling очереди (Этап 3) |
| `src/UpdateGenerator.php` | Генерация Telegram-like `Update` payload | Легко добавить методы для callback_query, edited_message |

### Выбор активного профиля и бота (cookie)

- **Хранение**: cookie `active_profile_id` / `active_bot_id` (устанавливаются на 30 дней)
- **Установка**: `POST /select-profile` и `POST /select-bot` — редирект обратно на текущую страницу
- **Чтение**: методы `Application::activeProfile()` / `Application::activeBot()` — читают из `$_COOKIE`
- **UI**: `<select onchange="this.form.submit()">` в шапке [`templates/layout.php`](templates/layout.php:199)
- **На будущее**: на Этапе 6 можно заменить на HTMX с сохранением в SQLite `settings`

### Поток данных

```
POST /chat/send { text }
  → Application::activeProfile() / activeBot() — из cookie
  → Валидация (профиль/бот включены)
  → MessageRepository::create() — сохраняет сообщение (direction=user)
  → UpdateGenerator::generate(message, profile, bot) — создаёт Update payload
  → UpdateRepository::create() — сохраняет update (queue_state=pending)
  → Редирект на GET /chat

GET /chat
  → activeProfile() / activeBot() — из cookie
  → Если не выбраны — показать подсказку «Выберите профиль и бота в шапке»
  → Загрузка messages (диалог)
  → Загрузка последнего update для inspector
  → Рендер templates/chat/index.php
```

### Генерация ID

- `message_id`: `MAX(message_id) + 1` для пары `bot_id + chat_id`
- `update_id`: `100000000 + id` (автоинкремент SQLite + базовое смещение)

### Маршруты

| Метод | Путь | Обработчик |
|---|---|---|
| `GET` | `/chat` | `chatIndex()` — экран чата (использует активный профиль/бота из cookie) |
| `POST` | `/chat/send` | `chatSend()` — отправка сообщения от активного профиля |
| `POST` | `/select-profile` | Установка cookie `active_profile_id`, редирект на Referer |
| `POST` | `/select-bot` | Установка cookie `active_bot_id`, редирект на Referer |

## Чеклист

- [pending] Создать `src/MessageRepository.php` (all, findByDialog, create)
- [pending] Создать `src/UpdateRepository.php` (create, findLatestByBot)
- [pending] Создать `src/UpdateGenerator.php` (generate — текст + bot_command entities)
- [pending] Добавить `activeProfile()`, `activeBot()` и маршруты `/chat`, `/chat/send`, `/select-profile`, `/select-bot` в `src/Application.php`
- [pending] Добавить `require` новых классов в `public/index.php`
- [pending] Создать `templates/chat/index.php` (история, поле ввода, inspector update)
- [pending] Добавить переключатели профиля/бота и ссылку «Чат» в `templates/layout.php`
- [pending] Проверить: cookie → сообщение → Update → inspector
- [pending] Коммит и пуш

## Важные заметки

- Таблицы `messages` и `updates` уже созданы миграцией — новые миграции не нужны.
- `queue_state = 'pending'` при создании update — готовит основу для Этапа 3 (Long Polling).
- Команды `/start`, `/help` и т.д. должны генерировать entity `bot_command`.
- Инспектор показывает raw JSON последнего update.
- Cookie-подход выбран для простоты MVP; на Этапе 6 можно заменить на HTMX + SQLite settings.

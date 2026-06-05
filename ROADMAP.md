# Roadmap

Примечание: этот `ROADMAP.md` и ранее существовавший файл `AI_PROPOSALS.md` были самостоятельно подготовлены и актуализированы AI-агентом Codex (ChatGPT 5.5) как рабочие инженерные ориентиры. Это не внешний контракт и не обещание полной реализации всех идей; каждое предложение ниже должно подтверждаться реальной пользовательской задачей, рисками и проверяемым результатом.

## 1. Текущая позиция проекта

TelegramClientEmulator — локальный Docker-first эмулятор Telegram Bot API для разработки и тестирования ботов без настоящего Telegram, BotFather, публичного HTTPS и тестовых аккаунтов.

Текущий статус: основной MVP локального developer workflow реализован.

MVP считается готовым для сценария, где разработчик:

- запускает эмулятор через Docker Compose;
- создает несколько локальных ботов с fake token;
- создает тестовых пользователей и group/supergroup чаты;
- отправляет сообщения через веб-чат;
- получает updates ботом через webhook или Long Polling;
- отправляет ответы через локальные Bot API routes;
- видит историю, payload, request/response и ошибки доставки в UI.

Проект не является полным Telegram server и не стремится реализовать весь Telegram Bot API заранее. Новые методы и payload добавляются только под реальные локальные сценарии разработки.

## 2. Архитектурные принципы

- Docker Compose остается основным способом запуска.
- Эмулятор не подключается к настоящему Telegram.
- Один глобальный `TELEGRAM_BOT_TOKEN` не используется: token является настройкой конкретного бота.
- Поддержка нескольких ботов и профилей является базовой моделью, а не расширением.
- Поведение Bot API routes должно оставаться каноничным относительно Telegram Bot API там, где метод поддержан.
- Неподдерживаемые методы возвращают Telegram-like ошибку с HTTP 501 и `ok=false`.
- UI строится на server-rendered HTML и HTMX без тяжелого SPA.
- Runtime-данные разработки хранятся локально в настраиваемых директориях и не коммитятся в git.
- Git и командная строка не вызываются из PHP; обновление проекта выполняется пользователем вручную.

## 3. Выбранный стек

- PHP 8.3+ как основной язык backend.
- HTMX для интерактивности без SPA.
- SQLite для локального хранения состояния.
- Встроенный PHP HTTP server внутри контейнера.
- Docker Compose для запуска эмулятора и соседних контейнеров ботов.

Стек выбран как прагматичный вариант для developer tool: формы, таблицы, чат, инспектор и небольшие HTTP endpoints быстрее поддерживать в server-rendered PHP, чем в отдельном frontend/backend SPA.

## 4. Реализованная Bot API surface

Текущий список также закреплен в [`docs/bot-api-surface.json`](docs/bot-api-surface.json).

Поддерживаются:

- `GET|POST /bot{token}/getMe`
- `GET|POST /bot{token}/getUpdates`
- `GET|POST /bot{token}/getFile`
- `POST /bot{token}/sendMessage`
- `POST /bot{token}/sendPhoto`
- `POST /bot{token}/sendDocument`
- `POST /bot{token}/sendVideo`
- `POST /bot{token}/sendAnimation`
- `POST /bot{token}/sendAudio`
- `POST /bot{token}/sendVoice`
- `POST /bot{token}/sendVideoNote`
- `POST /bot{token}/sendSticker`
- `POST /bot{token}/sendPoll`
- `POST /bot{token}/sendLocation`
- `POST /bot{token}/sendVenue`
- `POST /bot{token}/sendContact`
- `POST /bot{token}/sendDice`
- `POST /bot{token}/editMessageText`
- `POST /bot{token}/setWebhook`
- `POST /bot{token}/deleteWebhook`
- `GET|POST /bot{token}/getWebhookInfo`
- `POST /bot{token}/setMyCommands`
- `GET|POST /bot{token}/getMyCommands`
- `POST /bot{token}/deleteMyCommands`
- `POST /bot{token}/answerCallbackQuery`

Неподдерживаемые методы возвращают:

```json
{
  "ok": false,
  "error_code": 501,
  "description": "Метод пока не поддерживается эмулятором"
}
```

## 5. Этапы реализации

### Этап 0: фундамент проекта

Статус: реализовано.

Сделано:

- `docker-compose.yml` на `php:8.3-cli-alpine`;
- встроенный PHP HTTP server;
- endpoint `/health`;
- структура `public/`, `src/`, `templates/`, `migrations`, `data`, `var`;
- SQLite и миграции;
- bind mount проекта в `/app`;
- публикация порта через `HOST_PORT`, по умолчанию `8080`;
- подключение к Docker-сети через `APP_BACKEND_NETWORK`;
- `php.ini` с ручным парсингом request body.

### Этап 1: модель ботов и пользователей

Статус: реализовано.

Сделано:

- CRUD ботов;
- CRUD пользователей/profiles;
- fake token и bot id;
- выбор пары пользователь-бот через URL-параметры;
- SQLite-хранение настроек;
- inline validation основных форм;
- import/export bots/profiles.

### Этап 2: чат и генерация updates

Статус: реализовано.

Сделано:

- веб-чат;
- отправка сообщений от выбранного пользователя;
- генерация Telegram-like `Update`;
- генерация `message_id` и `update_id`;
- история сообщений;
- raw payload inspector последнего update;
- очистка истории выбранной пары пользователь-бот.

### Этап 3: Long Polling

Статус: реализовано.

Сделано:

- очередь updates по каждому боту;
- `GET|POST /bot{token}/getUpdates`;
- `offset`, `limit`, `timeout`, `allowed_updates`;
- подтверждение updates через offset;
- UI списка updates и фильтры;
- ограничение `timeout` через `LONG_POLLING_MAX_TIMEOUT_SECONDS` для защиты однопроцессного PHP server.

### Этап 4: webhook-доставка

Статус: реализовано для локального development workflow.

Сделано:

- `setWebhook`, `deleteWebhook`, `getWebhookInfo`;
- хранение webhook URL и secret token;
- режимы `webhook` и `long_polling`;
- 409 conflict для `getUpdates`, если webhook активен;
- POST-доставка update на webhook URL;
- сохранение request/response и ошибок;
- `/delivery-attempts` с фильтрами;
- Inspector для webhook request/response;
- timeout webhook delivery на панели;
- ручной retry failed delivery;
- batch retry failed updates на `/updates`;
- короткий synchronous development retry/backoff.

Ограничение: retry является локальным helper, а не production scheduler. Фоновых workers и отложенной очереди retry нет.

### Этап 5: ответы бота и UI сообщений

Статус: реализовано шире начального MVP.

Сделано:

- `sendMessage`;
- сохранение и отображение bot messages;
- JSON, form-urlencoded и multipart parsing;
- `reply_markup` для `inline_keyboard` и `keyboard`;
- callback query при клике по inline-кнопкам с `callback_data`;
- reply-кнопки как обычные message updates;
- URL inline-кнопки как ссылки;
- `setMyCommands`, `getMyCommands`, `deleteMyCommands`;
- command scopes и language-specific commands;
- компактный выбор команд в чате;
- `editMessageText` для текстовых сообщений бота.

Ограничения:

- admin-only command scopes в UI учитывают базовую роль `administrator`, но Telegram permissions не моделируются;
- URL-кнопки не создают update, потому что Telegram тоже не отправляет callback для обычного URL.

### Этап 6: media и structured-сообщения

Статус: частично реализовано, достаточно для основных локальных сценариев.

Сделано:

- `sendPhoto`, `sendDocument`, `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote`, `sendSticker`;
- строковые/URL media значения;
- multipart upload в каноничных media-полях;
- локальное media-хранилище через `MEDIA_DIR`;
- `getFile` и локальная отдача `/file/bot<TOKEN>/<file_path>`;
- `sendPoll` как read-only poll/quiz;
- `sendLocation`, `sendVenue`, `sendContact`, `sendDice`;
- отправка от пользователя media/structured-сообщений через компактный блок `Вложения` в чате.

Ограничения:

- интерактивное голосование в poll пока не моделируется;
- preview делается только для локальных изображений, неизвестные `file_id` и внешние URL остаются текстовым source;
- не все optional параметры Telegram Bot API реализованы.

### Этап 7: ergonomics и инспекторы

Статус: реализовано для текущего MVP.

Сделано:

- HTMX polling `/chat/fragment`;
- компактная вкладка `Чат` со скрываемым блоком контекста;
- навигация с активной вкладкой без дублирующих заголовков страниц;
- `/request-inspector` для Bot API HTTP logs и webhook delivery attempts;
- фильтры `status` и `ok=false`;
- curl-like view и pretty JSON;
- маскирование bot token и webhook secret token в HTML;
- `/updates` с фильтрами и batch-действиями;
- `/delivery-attempts`;
- `/group-chats` для group/supergroup чатов и membership через profiles;
- `/import-export` и fixture pack v2;
- Health-информация на вкладке `Панель` с raw endpoint `/health`.

### Этап 8: качество, документация и сопровождение

Статус: частично реализовано, продолжается.

Сделано:

- основной Docker HTTP smoke runner `tests/bot_api_test.php`;
- focused tests для parser, payload factory, reply markup, renderer, repositories и Long Polling;
- разбиение HTTP smoke scenarios на тематические файлы;
- `docs/adr-testing.md`;
- `docs/adr-routing.md`;
- `docs/limitations.md`;
- `docs/framework-examples.md`;
- `docs/bot-api-surface.json`;
- обновленный README с Windows/Docker/адресацией/reverse proxy;
- безопасная проверка обновлений на панели через локальный и удаленный `version.json` без запуска Git из PHP.

Осталось улучшить:

- сделать документацию короче в быстрых разделах, если появятся частые вопросы пользователей;
- добавить больше focused tests для новых UI-сценариев по мере роста интерфейса;
- при необходимости добавить release-процесс для автоматического обновления `version.json`.

## 6. Статус MVP

Ключевые приоритеты MVP:

1. Мультиботовая модель — реализовано.
2. Пользователи и чаты — реализовано.
3. Group/supergroup чаты — реализованы в базовой модели membership через profiles.
4. Генерация Telegram-like updates — реализовано для текста, callback, media и structured-сообщений.
5. Long Polling через `getUpdates` — реализовано.
6. Webhook configuration — реализовано.
7. Webhook delivery — реализовано для локального development workflow.
8. Ответы бота через локальный Bot API — реализовано для текущей supported surface.
9. Inspector payload/request/response/errors — реализовано.
10. Import/export тестового окружения — реализовано для JSON/fixture pack без бинарных media.
11. Windows/Docker README — реализовано.
12. Проверка обновлений без Git из PHP — реализовано.

Вывод: MVP как локальный инструмент разработки готов. Дальнейшая работа — расширение совместимости, улучшение документации и повышение покрытия тестами, а не закрытие базового сценария.

## 7. Открытые решения и будущие задачи

### Возможные предложения AI-агента

Этот раздел перенесен из `AI_PROPOSALS.md` после сверки с фактической реализацией. Большая часть прежних предложений уже выполнена: admin UI ботов и пользователей вынесен в отдельные контроллеры, import/export вынесен в `ImportExportController`, Bot API routes имеют registry, UI вложений покрывает текущую поддерживаемую media/structured surface, групповые чаты имеют отдельный экран, development webhook retry реализован, крупные message scenarios разделены.

Оставшиеся возможные предложения:

- Дальше уменьшать ответственность `Application`: при росте панели, health, update-check или media download можно вынести их в отдельные контроллеры без изменения URL и HTTP-контрактов.
- Разделить крупный `ImportExportController`, если он начнет мешать сопровождению: fixture pack validation/normalization можно вынести в отдельный service/helper.
- Добавить release-процесс, который автоматически обновляет `version.json` при публикации новой версии, чтобы проверка обновлений всегда сравнивала осмысленный release hash.
- Расширить group/supergroup модель более подробными Telegram permissions и дополнительными service events, если это понадобится для тестирования реальных ботов.
- Добавить export/import бинарных media архивом, если fixture pack нужно будет переносить между машинами вместе с файлами.
- Добавлять новые Bot API методы только под реальные сценарии: ближайшие кандидаты остаются `sendChatAction`, `deleteMessage`, `editMessageReplyMarkup`, `sendMediaGroup`.

### Telegram compatibility

- Добавлять новые Bot API методы только под реальные локальные сценарии.
- Возможные следующие кандидаты: `sendChatAction`, `deleteMessage`, `editMessageReplyMarkup`, `sendMediaGroup`.
- Для каждого нового метода нужно обновлять `docs/bot-api-surface.json`, tests, README/limitations при необходимости.

### Group/supergroup

- Текущая модель membership через profiles работает для базового тестирования.
- Title group/supergroup редактируется на `/group-chats/{chat_id}`, а история остается привязанной к `bot_id + chat_id`.
- Роли участников `member`/`administrator` редактируются на `/group-chats/{chat_id}`; admin role используется для UI-подбора admin command scopes без изменения exact Bot API.
- Service messages для изменения title, добавления и удаления участника отображаются в истории, но не создают Bot API updates.
- Отложено: подробная модель Telegram permissions и дополнительные service events.

### Media

- Fixture pack v2 сейчас хранит `media_manifest`, но не переносит бинарные media.
- Архивный export/import media нужен только при появлении реального сценария обмена fixture pack между машинами.

### Polls

- `sendPoll` реализован как read-only сообщение.
- Интерактивное голосование и poll answers пока не моделируются.

### Обновления

- Проверка обновлений безопасно сравнивает локальный `version.json` с удаленным `version.json`.
- PHP не выполняет `git pull` и не имеет доступа к shell.
- Будущий release-процесс может автоматически обновлять `version.json`, чтобы hash всегда соответствовал опубликованной сборке.

### Тестовая стратегия

- Самописный Docker HTTP smoke runner остается основным контуром.
- PHPUnit не добавляется без явной необходимости.
- Решение зафиксировано в [`docs/adr-testing.md`](docs/adr-testing.md).

### Routing

- Custom router остается текущим решением.
- Micro-framework не внедряется без явной необходимости.
- Решение зафиксировано в [`docs/adr-routing.md`](docs/adr-routing.md).

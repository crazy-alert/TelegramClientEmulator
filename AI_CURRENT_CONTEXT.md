# Текущий контекст проекта

## Последнее обновление

2026-06-03: выполнена `.aitasks/task07.md` — добавлен `/request-inspector`. Экран показывает последние Bot API request/response из HTTP JSONL-логов через новый `HttpLogRepository` и webhook request/response из `delivery_attempts`; поддержаны фильтры по raw token и Bot API method, но в HTML выводятся только замаскированные bot token и secret token. В layout добавлена ссылка `Inspector`. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task06.md` — добавлен раздел `/updates`. `UpdateRepository::allWithContext()` возвращает updates с context бота и пользователя, поддержаны фильтры `bot_id`, `profile_id`, `queue_state`, `update_id`. В layout добавлена ссылка `Updates`, создан `templates/updates/index.php` со списком, payload details, ссылкой в чат и ссылкой на delivery attempts. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task05.md` — добавлена inline validation для UI-форм ботов и пользователей. POST `/bots`, `/bots/<id>`, `/profiles`, `/profiles/<id>` теперь валидируют основные поля перед записью и при ошибках возвращают HTML формы со статусом 422 и `field-error` рядом с конкретным полем. Bot API маршруты не менялись. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task04.md` — добавлен HTMX polling для чата. `GET /chat/fragment?profile_id=<id>&bot_id=<id>` возвращает обновляемый фрагмент выбранной пары без формы выбора, а полная страница `/chat` содержит `hx-get`, `hx-trigger="every 3s"` и `hx-swap="innerHTML"`. Фрагмент сохраняет историю сообщений, raw inspector, resend failed webhook, inline keyboard и reply keyboard. `View` получил `renderPartial()`, layout подключает HTMX. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task03.md` — timeout webhook-доставки теперь виден и настраивается на панели `/`. Добавлен `SettingsRepository` для таблицы `settings`, маршрут `POST /settings/webhook-timeout`, валидация диапазона 1000–60000 мс и использование сохраненного значения в webhook-доставке. `WEBHOOK_TIMEOUT_MS` остается env/default, UI-настройка переопределяет его в SQLite. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task02.md` — добавлен экран `/delivery-attempts` для webhook delivery attempts. `DeliveryAttemptRepository::allWithContext()` возвращает attempts с update, bot и profile context, поддержаны фильтры `bot_id` и `update_id`. В навигацию добавлена ссылка `Webhook attempts`, шаблон `templates/delivery-attempts/index.php` показывает URL, HTTP status, duration, error, request/response body и ссылку назад в чат. `tests/bot_api_test.php` расширен smoke-проверкой списка и фильтров. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md`; будущая `.aitasks/task06.md` сужена до `/updates`, так как `/delivery-attempts` уже реализован.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task01.md` — добавлен ручной resend failed webhook delivery. Новый маршрут `POST /updates/<id>/resend` повторяет доставку только для `failed` update, использует сохраненный payload и текущие webhook-настройки бота, создает новую запись `delivery_attempts` и обновляет `queue_state` на `delivered` или `failed`. В inspector последнего update появилась кнопка resend для failed webhook. `tests/bot_api_test.php` поднимает локальный webhook receiver и проверяет failed delivery, кнопку resend и successful resend. Обновлены `README.md` и `ROADMAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: после проверки очереди `.aitasks` активных задач не найдено. README уже содержит раздел `Bot API и тесты` с командами запуска `tests/bot_api_test.php` и PHP lint. Обновлен `ROADMAP.md`, чтобы он отражал реализованные команды бота, inline/reply keyboard, callback query и начатые тесты. Создан `AI_PROPOSALS.md` с предложениями модернизации: декомпозиция `Application`, отдельный parser Bot API request parameters, `editMessageText`, экраны updates/delivery attempts, структурирование тестов, import/export и групповые чаты. Обновлена `AI_PROJECT_MAP.md`.

2026-06-03: исправлен лишний вертикальный отступ вокруг кликабельных команд в истории чата. Причина: многострочная HTML-форма команды попадала внутрь блока с `white-space: pre-wrap`, и браузер отображал шаблонные переносы/отступы как часть сообщения. Теперь форма `.message-command` выводится компактной строкой без промежуточных переносов; `pre-wrap` сохранен для пользовательского текста.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: исправлен рендер истории чата — команды вида `/start` и `/help` внутри текста сообщений теперь кликабельны. Команды рендерятся inline-формой `.message-command`, которая повторно отправляет команду через `/chat/send` для текущей пары пользователь-бот. `tests/bot_api_test.php` расширен проверкой HTML истории сообщений.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: добавлена поддержка команд и клавиатур Bot API. Новые методы: `setMyCommands`, `getMyCommands`, `deleteMyCommands`, минимальный `answerCallbackQuery`. Команды хранятся в `bot_commands` через миграцию `002_bot_commands.sql` и `BotCommandRepository`; `/chat` показывает выпадающий список и кликабельные команды. `sendMessage` принимает и возвращает `reply_markup`; интерфейс показывает `inline_keyboard` под сообщением бота и `keyboard` как основную клавиатуру. Reply-кнопки отправляют обычный текстовый message update, inline-кнопки с `callback_data` создают `callback_query` update. Обновлены `README.md`, `docs/technical-spec.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: добавлен ручной парсинг текстовых полей `multipart/form-data` при `enable_post_data_reading = Off`, поэтому `setWebhook` теперь читает `url`, `secret_token` и другие параметры из multipart-запросов bot frameworks. Добавлен интеграционный тест `tests/bot_api_test.php`, который поднимает встроенный PHP server во временной директории и проверяет реализованные Bot API методы, структуры `User`/`Chat`/`Message`/`Update`/`WebhookInfo`, ошибки валидации, конфликт `getUpdates` при активном webhook, фильтр `allowed_updates`, подтверждение offset и явные 501 для неподдерживаемых `editMessageText`/`answerCallbackQuery`. Обновлены `README.md` и `docs/technical-spec.md` по текущей поверхности Bot API, multipart-параметрам и запуску тестов.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

## Состояние

Проект: локальный эмулятор Telegram Bot API для разработки и тестирования ботов.

Реализовано:

- Docker runtime на готовом образе `php:8.3-cli-alpine` без локальной сборки через `Dockerfile`.
- Встроенный PHP HTTP server запускается напрямую через `docker-compose.yml`; nginx/sub_filter удалены после проверки malformed `multipart/form-data`.
- SQLite bootstrap и миграция `001_initial_schema.sql`.
- CRUD ботов через server-rendered PHP templates.
- Экран чата (`/chat`), отправка сообщений, генерация Telegram-like `Update`, inspector raw payload.
- CRUD пользователей через server-rendered PHP templates; исторически маршрут и таблица остаются `/profiles` и `profiles`.
- Экран чата (`/chat`) открывает диалог для явной пары пользователь-бот через query params `profile_id` и `bot_id`; cookie-состояние выбора больше не используется, поэтому разные вкладки могут работать с разными пользователями и ботами.
- Форма создания бота автоматически генерирует `bot_id` и `token`; token соответствует `/\d{5,10}[:][a-zA-Z0-9_.+-]{15,}/`, показывается как placeholder и отправляется скрытым полем, если пользователь не ввел свой token.
- Bot API: `GET|POST /bot<TOKEN>/getMe`.
- Bot API: `GET|POST /bot<TOKEN>/getUpdates` отдаёт pending updates Long Polling, поддерживает `offset`, `limit`, `timeout`, `allowed_updates`, подтверждает updates с `update_id < offset`, возвращает 409 при активном webhook.
- Bot API: `GET|POST /bot<TOKEN>/getWebhookInfo` возвращает `url`, `has_custom_certificate=false`, `pending_update_count` и `max_connections=40`.
- Bot API: `POST /bot<TOKEN>/sendMessage` принимает JSON и form-urlencoded body, требует `chat_id` и `text`, ищет включенного пользователя по `chat_id`, сохраняет сообщение направления `bot` в историю и возвращает Telegram-like `Message`.
- Bot API: `POST /bot<TOKEN>/setWebhook` сохраняет webhook URL, optional `secret_token` и переключает бота в `delivery_mode=webhook`; пустой `url` очищает webhook и возвращает `long_polling`.
- Bot API: `POST /bot<TOKEN>/deleteWebhook` очищает webhook, переключает бота в `delivery_mode=long_polling`; при `drop_pending_updates=true` удаляет pending updates бота.
- Webhook delivery loop: при `delivery_mode=webhook` и настроенном `webhook_url` созданный update отправляется POST-запросом с JSON body, `Content-Type: application/json` и optional `X-Telegram-Bot-Api-Secret-Token`; попытка сохраняется в `delivery_attempts`, update получает `queue_state=delivered` или `failed`.
- Timeout webhook-доставки настраивается на панели `/` в диапазоне 1000–60000 мс; значение хранится в SQLite `settings`, а `WEBHOOK_TIMEOUT_MS` задает только начальный default.
- HTTP-логирование: каждый запрос пишет JSONL-событие в `LOG_DIR` или `var/logs/http-YYYY-MM-DD.jsonl` с request headers/body, response status/headers/body, duration и error; файлы `http-*.jsonl` старше 5 дней удаляются автоматически при запросах.
- Chat UI показывает размер pending-очереди Long Polling для активного бота.
- Chat UI показывает последнюю webhook delivery attempt для последнего update.
- Chat UI периодически обновляет выбранную пару пользователь-бот через HTMX-фрагмент `/chat/fragment`.
- `/updates` показывает updates без чтения SQLite вручную: фильтры по боту, пользователю, `queue_state`, `update_id`, payload details и переходы в чат/attempts.
- `/request-inspector` показывает Bot API request/response из HTTP-логов и webhook request/response из delivery attempts; UI маскирует bot token и secret token.
- В UI терминология `Профиль/Профили` заменена на `Пользователь/Пользователи`; у пользователя больше нет полей `Название профиля` и `Активный бот`, имя в БД заполняется из `username`.
- UI-формы ботов и пользователей показывают основные ошибки рядом с полями и не записывают некорректные данные.

## Важные решения

- Основной язык: PHP, стиль K&R для фигурных скобок.
- Интерактивность интерфейса: server-rendered PHP templates, HTMX планируется дальше.
- Хранение: SQLite в `data/telegram_emulator.sqlite`.
- Выбор пользователя и бота для чата хранится в URL, а не в cookie, чтобы разные вкладки могли работать с разными диалогами.
- Проект не привязан к одному bot token.
- Обязательны оба режима получения updates: webhook и Long Polling.
- Постоянное правило из `AGENTS.md`: поведение маршрутов, payload, параметров, кодов ошибок и семантики Telegram Bot API должно быть каноничным; неканоничные aliases/shortcuts/альтернативные URL-формы нельзя добавлять без явного запроса пользователя.
- Для локального Docker workflow `setWebhook` принимает `http` и `https` URL, включая service DNS вроде `http://bot:3000/webhook`.
- `php.ini` отключает автоматическое чтение POST-данных (`enable_post_data_reading = Off`), а приложение вручную парсит JSON и form-urlencoded body. Это устраняет warning встроенного PHP-сервера без reverse proxy.
- В документации не использовать буквальную запись `{token}` в URL: некоторые HTTP-клиенты считают `{}` malformed URL. Эмулятор повторяет форму настоящего Telegram Bot API: `/bot<TOKEN>/<METHOD>`, без дополнительного `/` между `bot` и token.
- `getUpdates.timeout` в MVP ограничен коротким ожиданием до 3 секунд, чтобы не блокировать single-process встроенный PHP server надолго.
- Webhook delivery в MVP делает одну попытку без retry; timeout берется из UI-настройки `webhook_timeout_ms`, если она сохранена, иначе из `WEBHOOK_TIMEOUT_MS`, и ограничивается диапазоном 1000–60000 мс.
- Логи находятся в runtime-директории `var/logs/` и исключены из git.
- Групповой сценарий запланирован как несколько сохраненных пользователей с одним `chat_id`, выбор отправителя в group chat и доставка updates выбранному боту; отдельная сущность группы пока не введена.

## Проверки

- 2026-05-27: обновлен `ROADMAP.md` под текущий статус: реализованные Bot API методы, фактический Docker runtime без `Dockerfile`, статусы этапов 0-5, следующий крупный этап webhook delivery.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка в контейнере через `docker run -p 127.0.0.1:18081:8080 ...`:
  - создание тестового бота через `/bots`;
  - `POST /bot123456:local-dev-token/setWebhook` с `url=http://bot:3000/webhook&secret_token=test-secret` вернул `{"ok":true,"result":true}`;
  - `url=not-a-url` вернул HTTP 400;
  - SQLite содержит `delivery_mode=webhook`, `webhook_url=http://bot:3000/webhook`, `webhook_secret_token=test-secret`.
- 2026-05-27: прямой `php -S` на `php:8.3-cli-alpine` с `Content-Type: multipart/form-data` без boundary вернул чистый JSON `404` без warning в body и логах.
- 2026-05-27: `docker compose up -d` поднял `telegram-emulator`, контейнер `healthy`, `GET http://127.0.0.1:8080/health` вернул HTTP 200.
- 2026-05-27: канонический `GET /bot123456:local-dev-token/getMe` возвращает Telegram-like JSON 404 для отсутствующего тестового token.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка в одноразовом `php:8.3-cli-alpine` контейнере:
  - `/bots/new` вернул hidden `generated_token`, совпадающий с regex token;
  - создание бота без ручного token/id сохранило token из placeholder и `bot_id` из префикса token;
  - `POST /bot<TOKEN>/setWebhook` вернул `{"ok":true,"result":true,"description":"Webhook was set"}`;
  - `POST /bot<TOKEN>/deleteWebhook` вернул `{"ok":true,"result":true,"description":"Webhook was deleted"}`;
  - SQLite содержит `delivery_mode=long_polling`, пустые `webhook_url` и `webhook_secret_token`.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка Long Polling в одноразовом `php:8.3-cli-alpine` контейнере:
  - создан бот и пользователь, `/chat/send` создал update;
  - `GET /bot<TOKEN>/getUpdates` вернул один update с реальным `update_id` и текстом `/start`;
  - повторный `getUpdates?offset=<update_id+1>` вернул пустой массив и перевёл update в `queue_state=confirmed`;
  - `allowed_updates=["callback_query"]` отфильтровал message update;
  - при активном webhook `POST /bot<TOKEN>/getUpdates` вернул Telegram-like 409 conflict.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка `sendMessage` в одноразовом `php:8.3-cli-alpine` контейнере:
  - form-urlencoded `POST /bot<TOKEN>/sendMessage` сохранил сообщение бота и вернул `message_id=1`;
  - JSON `POST /bot<TOKEN>/sendMessage` сохранил второе сообщение и вернул `message_id=2`, `chat.id=1001`;
  - неизвестный `chat_id` вернул Telegram-like `400`;
  - SQLite содержит 2 сообщения направления `bot`.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка `getWebhookInfo` в одноразовом `php:8.3-cli-alpine` контейнере:
  - до настройки webhook метод вернул пустой `url` и `pending_update_count=1`;
  - после `setWebhook` метод вернул `url=http://bot:3000/webhook`, `has_custom_certificate=false`, `max_connections=40`, `pending_update_count=1`;
  - неизвестный token вернул Telegram-like `404`.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка webhook delivery в одноразовом `php:8.3-cli-alpine` контейнере:
  - подняты два локальных PHP server: приложение и webhook receiver;
  - сообщение из `/chat/send` при `delivery_mode=webhook` отправило update на receiver;
  - receiver получил JSON body с реальным `update_id` и `message.text=/start`;
  - receiver получил `X-Telegram-Bot-Api-Secret-Token`;
  - SQLite содержит `updates.queue_state=delivered`, `delivered_at`, `delivery_attempts.response_status=202`, response body и пустой error;
  - `getWebhookInfo` после доставки вернул `pending_update_count=0`.
- 2026-05-27: `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — синтаксических ошибок нет.
- 2026-05-27: HTTP-проверка выбора пользователя/бота без cookie в одноразовом `php:8.3-cli-alpine` контейнере:
  - форма пользователя не содержит `Название профиля`, `Активный бот`, `nav-right`;
  - создание пользователя сохраняет `name=username` и `active_bot_id=NULL`;
  - `/chat?profile_id=<user1>&bot_id=<bot1>` и `/chat?profile_id=<user2>&bot_id=<bot2>` открывают разные диалоги;
  - `/chat/send` с hidden `profile_id`/`bot_id` сохраняет сообщения в правильные пары пользователь-бот;
  - cookie-поля выбора не попадают в HTML чата.

## Замечания

- На хосте нет `php` в PATH, проверки PHP выполнялись внутри Docker.

## Ближайший следующий этап

Следующий практичный этап: групповые чаты — несколько пользователей в одном `chat_id`, выбор отправителя и проверка реакции бота в общем диалоге.

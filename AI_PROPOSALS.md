# Предложения по модернизации проекта

Файл обновлен после завершения очереди `.aitasks` 2026-06-05. Активных task-файлов сейчас нет.

Ниже только актуальные предложения. Уже выполненные пункты не повторяются как будущие задачи: декомпозиция `BotApiRequestParser`, `ChatController`, `ReplyMarkup`, `MediaStorage`, `MessageRenderer`, `BotApiParams`, `BotApiPayloadFactory`, поддержка базовых structured/media methods, multipart upload для `sendPhoto`/`sendDocument`, `getFile`, локальная отдача media и focused tests.

## Высокий приоритет

### 1. Вынести Long Polling в отдельный сервис

`BotApiController` уже стал меньше, но `getUpdates` все еще держит логику очереди, offset confirmation, фильтрации `allowed_updates` и короткого ожидания. Это отдельная ответственность и ее удобно тестировать без HTTP.

Минимальный scope:

- добавить `LongPollingService`;
- перенести туда выбор pending updates, подтверждение offset и фильтрацию allowed updates;
- оставить в `BotApiController` проверку bot/webhook conflict и формирование HTTP response;
- добавить focused tests для offset, negative offset, limit и `allowed_updates`;
- не менять текущий лимит ожидания и тексты HTTP-ошибок.

Ожидаемый эффект: проще развивать `getUpdates`, меньше риска сломать Bot API routes при изменениях очереди.

### 2. Расширить multipart upload на остальные media-методы

Сейчас реальные файлы поддержаны для `sendPhoto` и `sendDocument`. `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote` и `sendSticker` принимают только строковый URL/file_id.

Минимальный scope:

- принимать multipart file parts в каноничных полях соответствующих методов;
- переиспользовать `MediaStorage`;
- возвращать `file_size`, `mime_type`, `file_name` там, где это есть в Telegram-like object;
- добавить HTTP tests для success, required param, unknown chat и `getFile`;
- обновить `README.md`, `docs/technical-spec.md`, `docs/limitations.md` и `ROADMAP.md`.

Ожидаемый эффект: локальная проверка ботов с audio/video/sticker сценариями станет ближе к Telegram.

### 3. Сделать UI-preview и download links для локальных media

Чат показывает media blocks как текстовые `file_id`/source. После `getFile` и `/file/bot<TOKEN>/<file_path>` можно безопасно дать ссылку на локальный файл.

Минимальный scope:

- для `local-media:<sha256>` показывать ссылку "Скачать" в media block;
- для локальных изображений показывать компактный `<img>` preview;
- не preview-ить внешние URL;
- сохранить лаконичную верстку `/chat` для маленьких экранов;
- добавить DOM/HTTP regression checks.

Ожидаемый эффект: media-сообщения станут полезными в интерфейсе, а не только в payload.

### 4. Дальше декомпозировать `Application`

`src/Application.php` все еще совмещает composition root, router, dashboard/settings, bots/profiles forms, updates/delivery attempts, request inspector и import/export.

Практичный следующий срез:

- `BotAdminController` для `/bots`;
- `ProfileAdminController` для `/profiles`;
- `ImportExportController` для `/import-*` и `/export-*`;
- `InspectorController` для `/updates`, `/delivery-attempts`, `/request-inspector`;
- оставить `Application` как bootstrapping, DI/composition root и route dispatcher.

Ожидаемый эффект: меньше риск при добавлении UI-экранов, проще тестировать validation/import logic отдельно от маршрутизации.

## Средний приоритет

### 5. Дробить HTTP scenarios дальше

`tests/scenarios/http_scenarios.php` остается крупным файлом.

Следующий срез:

- `chat_ui_scenarios.php`;
- `media_scenarios.php`;
- `webhook_scenarios.php`;
- `long_polling_scenarios.php`;
- `import_export_scenarios.php`;
- общий entrypoint `tests/bot_api_test.php` оставить прежним.

Ожидаемый эффект: проще искать failing scenario и добавлять новые Bot API проверки.

### 6. Улучшить webhook retry/backoff

Сейчас webhook delivery делает одну попытку, failed update можно переотправить вручную.

MVP без production scheduler:

- настройка количества retry и задержки в UI;
- синхронные короткие retry в рамках текущего запроса или manual batch retry для failed updates;
- отдельное логирование попыток;
- явно документировать, что это development helper.

### 7. Ввести отдельную модель group chat

Текущая group/supergroup модель работает через несколько `profiles` с общим `chat_id`. Этого достаточно для первых тестов, но не покрывает title, membership, роли и service messages.

Первый шаг:

- добавить сущность `chats` или `groups` с `chat_id`, `type`, `title`;
- связать profiles с group через membership table;
- сохранить быстрый сценарий выбора отправителя в `/chat`;
- продумать backward-compatible import.

### 8. Расширить import/export до fixture packs

Текущий import/export сохраняет bots/profiles без истории. Для повторяемых тестовых сценариев полезны fixture packs.

Scope:

- optional export/import `bot_commands`;
- optional export/import `groups/chats`, если появится отдельная модель;
- позже optional `messages`/`updates` без delivery attempts;
- отдельное решение для media archive/manifest, не бинарные файлы внутри JSON.

## Низкий приоритет

### 9. Уточнить Long Polling timeout модель

`getUpdates.timeout` ограничен 3 секундами из-за single-process PHP server. Это осознанное ограничение, но некоторым bot frameworks важно более похожее long polling поведение.

Варианты:

- оставить ограничение и добавить больше framework-документации;
- разрешить настройку верхней границы timeout в UI/env;
- перейти на runtime/server mode, где блокирующее ожидание не мешает другим запросам.

### 10. Поддержать command scopes и language-specific commands

`setMyCommands` хранит default-команды без scope и language. Для MVP нормально, но не покрывает разные команды для group/private chats и языков.

Минимальный следующий шаг: сохранять raw `scope` и `language_code`, но сначала решить, как UI будет выбирать набор команд для текущего chat/profile.

### 11. Улучшить inspector HTTP-логов

Идеи:

- фильтр по HTTP status и `ok=false`;
- копирование curl-like request;
- раскрытие JSON body в pretty tree;
- связь request inspector с конкретным message/update.

### 12. Добавить machine-readable описание локального Bot API surface

Документация сейчас человекочитаемая. Для examples и будущих tests можно добавить простой JSON/YAML каталог поддерживаемых методов.

Формат может содержать:

- method name;
- HTTP methods;
- required/optional params;
- supported content types;
- media upload support;
- known limitations;
- тестовый статус.

## Ближайшая практическая задача

Наиболее полезный следующий шаг: вынести `getUpdates` queue/offset/allowed_updates logic в `LongPollingService` с focused tests. Это продолжит декомпозицию без изменения Bot API surface и подготовит почву для более точной timeout-модели.

# Предложения по модернизации проекта

Файл обновлен после выполнения очереди `.aitasks`: активных задач больше нет. Ниже только актуальные предложения; уже выполненные пункты вроде `BotApiController`, `ChatController`, `BotApiRequestParser`, `ReplyMarkup`, структурирования тестового runner и DOM-проверок сюда не повторяются как будущие задачи.

## Высокий приоритет

### 1. Дальше декомпозировать `Application`

`src/Application.php` стал меньше, но все еще совмещает custom router, dashboard/settings, bots/profiles forms, updates/delivery attempts, request inspector, import/export и общие validation helpers.

Предлагаемый следующий срез:

- `BotAdminController` для `/bots`;
- `ProfileAdminController` для `/profiles`;
- `ImportExportController` для `/import-*` и `/export-*`;
- `InspectorController` для `/updates`, `/delivery-attempts`, `/request-inspector`;
- оставить `Application` как composition root, bootstrapping и route dispatcher.

Ожидаемый эффект: меньше риска при добавлении новых UI-экранов и проще поддерживать validation/import logic отдельно от маршрутизации.

### 2. Разделить `BotApiController` на методы и payload services

`src/BotApiController.php` уже содержит routing Bot API, validation параметров, Long Polling queue, message payload builders и media placeholder builders.

Предлагаемый шаг:

- вынести `getUpdates` в `LongPollingController` или `LongPollingService`;
- вынести создание Telegram-like `Message`, `Chat`, `PhotoSize`, `Document` payload в `BotApiPayloadFactory`;
- оставить `BotApiController` диспетчером методов и владельцем HTTP status/response.

Ожидаемый эффект: проще добавлять новые Bot API methods без копирования validation и payload builders.

### 3. Реализовать следующий media method: `sendVideo`

Roadmap уже указывает `sendVideo` как следующий метод. После `sendPhoto` и `sendDocument` его можно добавить тем же ограниченным MVP-подходом.

Минимальный scope:

- `POST /bot<TOKEN>/sendVideo`;
- параметры `chat_id`, `video`, optional `caption`, optional `reply_markup`;
- строковое/URL значение `video`, без multipart file upload;
- Telegram-like `Message.video` с `file_id`, `file_unique_id`, `width`, `height`, `duration`;
- отображение video placeholder в чате;
- тесты JSON/form-urlencoded и HTML render.

### 4. Улучшить webhook retry/backoff

Сейчас webhook delivery делает одну попытку, а failed update можно переотправить вручную. Для локальной отладки ботов полезен управляемый retry без production-инфраструктуры.

Предлагаемый MVP:

- настройка количества retry и задержки в UI;
- синхронные короткие retry в рамках текущего запроса или явный manual batch retry для failed updates;
- отдельное логирование попыток без фонового worker на первом шаге;
- ясное ограничение, что это development helper, а не production scheduler.

## Средний приоритет

### 5. Ввести отдельную модель group chat

Текущая group/supergroup модель работает через несколько `profiles` с общим `chat_id`. Этого достаточно для первых тестов, но не покрывает title, membership, роли и service messages.

Предлагаемый первый шаг:

- добавить сущность `chats` или `groups` с `chat_id`, `type`, `title`;
- связать profiles с group через membership table;
- сохранить текущий быстрый сценарий выбора отправителя в `/chat`;
- обновить import/export format v2 или добавить backward-compatible import.

### 6. Расширить import/export до fixtures

Текущий import/export сохраняет bots/profiles без истории. Для повторяемых тестовых сценариев полезны fixture packs.

Предлагаемый scope:

- optional export/import `bot_commands`;
- optional export/import `groups/chats`, если появится отдельная модель;
- позже optional `messages`/`updates` без delivery attempts;
- документировать versioning и конфликтные стратегии до реализации.

### 7. Дробить HTTP scenarios дальше

`tests/scenarios/http_scenarios.php` уже вынесен из entrypoint, но остается крупным файлом.

Предлагаемый следующий срез:

- `chat_ui_scenarios.php`;
- `webhook_scenarios.php`;
- `long_polling_scenarios.php`;
- `import_export_scenarios.php`;
- общий entrypoint `tests/bot_api_test.php` оставить прежним.

Ожидаемый эффект: легче добавлять новые Bot API методы и быстрее находить failing scenario.

### 8. Уточнить Long Polling timeout модель

`getUpdates.timeout` ограничен 3 секундами из-за single-process PHP server. Это осознанное ограничение, но некоторым bot frameworks важно более похожее long polling поведение.

Варианты:

- оставить текущее ограничение и добавить больше документации для frameworks;
- разрешить настройку верхней границы timeout в UI/env;
- перейти на runtime/server mode, где блокирующее ожидание не мешает другим запросам.

## Низкий приоритет

### 9. Поддержать command scopes и language-specific commands

Сейчас `setMyCommands` хранит default-команды без scope и language. Это нормально для MVP, но не покрывает ботов с разными командами для групп, private chats или языков.

Минимальный следующий шаг: сохранить raw `scope` и `language_code`, но сначала решить, как UI будет выбирать набор команд для текущего chat/profile.

### 10. Улучшить инспектор HTTP-логов

Инспектор уже показывает Bot API logs и webhook attempts, но может стать удобнее для ежедневной отладки.

Идеи:

- фильтр по HTTP status и `ok=false`;
- копирование curl-like request;
- раскрытие JSON body в pretty tree;
- связка request inspector с конкретным message/update.

### 11. Добавить machine-readable описание локального Bot API surface

Документация сейчас человекочитаемая. Для bot framework examples и будущих tests можно добавить простой JSON/YAML каталог поддерживаемых методов.

Формат может содержать:

- method name;
- HTTP methods;
- required/optional params;
- supported content types;
- known limitations;
- тестовый статус.

## Ближайшая практичная задача

Наиболее полезная следующая задача: базовый `sendVideo` по аналогии с `sendPhoto`/`sendDocument`. Она расширяет уже сложившуюся media-модель, проверит готовность `BotApiPayloadFactory` после возможной декомпозиции и даст понятный пользовательский результат без необходимости сразу поддерживать file upload.

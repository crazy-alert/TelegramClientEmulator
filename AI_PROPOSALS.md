# Предложения по модернизации проекта

Файл обновлен после завершения очереди `.aitasks` 2026-06-05. Активных task-файлов на момент анализа не было.

Ниже только актуальные предложения. Уже выполненные пункты не повторяются как будущие задачи: `LongPollingService`, command scopes, fixture packs, `MediaStorage`, media preview/download, расширенные media methods, `MessageRenderer`, `ReplyMarkup`, `BotApiParams`, `BotApiPayloadFactory`, request inspector, webhook retry UI, групповые `chats/chat_members` и machine-readable каталог Bot API surface уже реализованы.

## Высокий приоритет

### 1. Вынести admin UI ботов и пользователей из `Application`

`src/Application.php` все еще содержит routing, dashboard/settings, forms для ботов и пользователей, import/export, media download и общие helpers. Это делает любые UI-изменения рискованнее, чем нужно.

Минимальный scope:

- добавить `BotAdminController` для `/bots`, `/bots/new`, `/bots/{id}/edit`, create/update;
- добавить `ProfileAdminController` для `/profiles`, `/profiles/new`, `/profiles/{id}/edit`, create/update;
- оставить в `Application` composition root, общий dispatcher и shared helpers только там, где они действительно общие;
- сохранить текущие URL, HTTP status, redirects и тексты ошибок;
- добавить focused HTTP/DOM checks для страниц forms.

Ожидаемый эффект: меньше связность `Application`, проще менять админские формы и не затрагивать Bot API/chat routes.

### 2. Вынести import/export и fixture pack logic из `Application`

Import/export занимает значительную часть `Application`: export payload, validation, fixture pack, conflict checks и нормализация включенных секций. Это самостоятельная область с отдельными правилами безопасности и совместимости.

Минимальный scope:

- добавить `ImportExportController` для `/import-export`, `/export/*`, `/import/*`;
- вынести validation/normalization fixture pack в отдельный helper/service, если контроллер иначе получится слишком крупным;
- сохранить JSON envelope v1 и текущие ограничения;
- добавить tests на конфликт `chat_id`, bot command sections и отказ от бинарных media внутри JSON.

Ожидаемый эффект: import/export можно развивать как fixture-инструмент без дальнейшего разрастания `Application`.

### 3. Сделать registry маршрутов Bot API

`BotApiController::handle()` содержит длинную цепочку `preg_match` по каждому методу. После расширения Bot API surface это стало шумным местом: добавление метода требует ручного копирования маршрута, handler и обновления каталога.

Минимальный scope:

- ввести локальную таблицу route definitions: method name, HTTP verbs, handler;
- сохранить каноничный URL `/bot<TOKEN>/<METHOD>` и текущие ошибки unsupported methods;
- не добавлять aliases или альтернативные пути;
- обновить `tests/bot_api_surface_catalog_test.php`, чтобы он сверял каталог с registry;
- проверить, что `GET|POST`-методы и `POST`-only методы ведут себя как раньше.

Ожидаемый эффект: проще поддерживать surface catalog и снижать риск рассинхронизации маршрутов.

### 4. Расширить UI отправки вложений до всей поддерживаемой Bot API surface

Backend уже поддерживает больше типов сообщений, чем форма `/chat`: сейчас UI-вложения покрывают только photo, document, location и contact. Для локального тестирования ботов нужна отправка всех основных типов как из мессенджера.

Минимальный scope:

- добавить компактные формы для `video`, `animation`, `audio`, `voice`, `video_note`, `sticker`, `poll`, `venue`, `dice`;
- сохранить раскрывающийся блок `Вложения` и плотную верстку для маленьких экранов;
- переиспользовать существующий `/chat/send` и `message_type`;
- добавить DOM/HTTP checks на наличие форм и создание updates;
- обновить `README.md`/`docs/limitations.md`, если меняется описание UI.

Ожидаемый эффект: frontend перестанет отставать от реализованного backend Bot API.

## Средний приоритет

### 5. Добавить экран управления групповыми чатами и участниками

Сущности `chats` и `chat_members` уже есть, но пользователь управляет группами косвенно через profiles/import. Это неочевидно и мешает вручную собирать групповые сценарии.

Минимальный scope:

- добавить UI для списка group/supergroup chats с title, `chat_id`, type;
- добавить управление участниками через существующие profiles;
- сохранить текущую совместимость profiles с `chat_id`;
- добавить validation на конфликт private/group `chat_id`;
- покрыть happy path и конфликтные cases тестами.

Ожидаемый эффект: group chat scenarios станут явной частью продукта, а не побочным эффектом profiles.

### 6. Улучшить development webhook retry/backoff

Сейчас есть ручной retry failed delivery и batch retry, но нет настраиваемой модели коротких повторов с задержкой. Для локальной отладки нестабильных ботов полезно видеть несколько попыток без production scheduler.

Минимальный scope:

- добавить настройки max attempts и delay для development retry;
- выполнять короткие синхронные retry только в рамках локального helper-режима;
- показывать попытки в inspector/delivery attempts без скрытия ошибок;
- явно документировать ограничение: это не production scheduler.

Ожидаемый эффект: проще отлаживать временные ошибки webhook endpoint без ручного повторения каждого update.

### 7. Дробить крупные message scenarios

`tests/scenarios/bot_api_message_scenarios.php` стал самым крупным сценарным файлом. Он покрывает text, edit, media, structured payloads и validation, поэтому failures труднее локализовать.

Минимальный scope:

- разделить сценарии на message core, media methods и structured methods;
- не менять общий entrypoint `tests/bot_api_test.php`;
- сохранить порядок setup и независимость runtime;
- добавить короткие названия сценариев в failure messages, где это помогает.

Ожидаемый эффект: тесты проще читать, расширять и запускать точечно при новых Bot API методах.

## Ближайшая практическая задача

Наиболее полезный следующий шаг: вынести admin UI ботов и пользователей из `Application`. Это снижает архитектурный риск без изменения Bot API surface и даст основу для дальнейшего выноса import/export.

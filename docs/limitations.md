# Ограничения эмулятора

Эмулятор предназначен для локальной разработки Telegram-ботов в Docker Compose. Он не подключается к настоящему Telegram, не принимает production traffic и не заменяет end-to-end проверки против настоящей Telegram-платформы.

## Поддерживаемая поверхность Bot API

Сейчас эмулятор поддерживает только эти Bot API endpoints:

- `GET|POST /bot<TOKEN>/getMe`
- `GET|POST /bot<TOKEN>/getUpdates`
- `GET|POST /bot<TOKEN>/getFile`
- `POST /bot<TOKEN>/sendMessage`
- `POST /bot<TOKEN>/sendPhoto`
- `POST /bot<TOKEN>/sendDocument`
- `POST /bot<TOKEN>/sendVideo`
- `POST /bot<TOKEN>/sendAnimation`
- `POST /bot<TOKEN>/sendAudio`
- `POST /bot<TOKEN>/sendVoice`
- `POST /bot<TOKEN>/sendVideoNote`
- `POST /bot<TOKEN>/sendSticker`
- `POST /bot<TOKEN>/sendPoll`
- `POST /bot<TOKEN>/sendLocation`
- `POST /bot<TOKEN>/sendVenue`
- `POST /bot<TOKEN>/sendContact`
- `POST /bot<TOKEN>/sendDice`
- `POST /bot<TOKEN>/editMessageText`
- `GET|POST /bot<TOKEN>/getWebhookInfo`
- `POST /bot<TOKEN>/setWebhook`
- `POST /bot<TOKEN>/deleteWebhook`
- `POST /bot<TOKEN>/setMyCommands`
- `GET|POST /bot<TOKEN>/getMyCommands`
- `POST /bot<TOKEN>/deleteMyCommands`
- `POST /bot<TOKEN>/answerCallbackQuery`

Если метода нет в этом списке, он считается неподдерживаемым. Эмулятор должен вернуть Telegram-like JSON с HTTP 501, `ok=false`, `error_code=501` и понятным `description`, а не молчаливую заглушку.

## Неподдерживаемые области

Эмулятор пока не реализует:

- полный Telegram Bot API;
- подключение к настоящему Telegram;
- download для внешних/неизвестных Telegram `file_id`;
- media/structured-методы за пределами базовых `sendPhoto`, `sendDocument`, `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote`, `sendSticker`, `sendPoll`, `sendLocation`, `sendVenue`, `sendContact` и `sendDice`;
- payments, invoices, shipping/pre-checkout queries;
- Telegram Passport;
- games;
- forum topics, business connections и другие специальные режимы платформы;
- inline mode через `inline_query`;
- membership/admin workflows вроде `getChatMember`, `banChatMember`, `restrictChatMember`;
- production-grade webhook retry/backoff, scheduler и фоновые delivery workers.

Это не полный каталог всех методов Telegram Bot API. Практическое правило проще: все, что не перечислено в разделе поддерживаемой поверхности, сейчас не поддерживается.

## Media upload

`sendPhoto`, `sendDocument`, `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote` и `sendSticker` принимают строковое/URL значение соответствующего media-поля или multipart upload в каноничном media-поле метода. Загруженные файлы сохраняются в локальном `MEDIA_DIR`, получают стабильный `file_id` вида `local-media:<sha256>` и `file_unique_id` на основе содержимого.

Для typed media upload эмулятор возвращает доступные metadata (`file_size`, `mime_type`, `file_name`) только там, где такие поля есть в соответствующем Telegram-like object. Для `setWebhook` и других POST-методов текстовые поля `multipart/form-data` поддерживаются, но файловые части используются только в media-методах, где это явно реализовано.

`getFile` возвращает Telegram-like `File` только для локально сохраненных `local-media:<sha256>` файлов. Скачать такой файл можно по `GET /file/bot<TOKEN>/<file_path>`; endpoint проверяет bot token и отдает только файлы из `MEDIA_DIR`.

В `/chat` для найденных локальных `local-media:<sha256>` показывается ссылка "Скачать". Preview показывается только для локальных файлов с image content type; внешние URL и неизвестные `file_id` не скачиваются и не preview-ятся.

Ограничения:

- внешние Telegram `file_id` и URL не скачиваются;
- размер одного upload ограничен `MEDIA_MAX_BYTES`, по умолчанию `10485760` байт;
- имена файлов очищаются от путей и небезопасных символов; path traversal не сохраняется;
- `file_path` не принимает вложенные директории и `..`;
- бинарные файлы не входят в import/export JSON.

## Structured сообщения

`sendLocation`, `sendVenue`, `sendContact` и `sendDice` принимают обязательные параметры Telegram Bot API, сохраняют structured payload в истории чата и возвращают Telegram-like `Message.location`, `Message.venue`, `Message.contact` или `Message.dice`.

`sendPoll` принимает `question`, `options` и базовые optional параметры regular/quiz poll. Эмулятор сохраняет poll как read-only сообщение и возвращает Telegram-like `Message.poll`.

UI чата может отправлять от пользователя photo/document по URL, file_id или локальному файлу, location и contact. Эти сообщения создают обычный `message` update для webhook и Long Polling.

Ограничения:

- `sendDice` возвращает детерминированное значение: `4` для обычных dice emoji и `32` для slot machine, чтобы локальные тесты были стабильными;
- интерактивное голосование в poll, карты и внешние previews пока не моделируются;
- полноценные previews и загрузка внешних media URL не моделируются.

## Команды бота

`setMyCommands`, `getMyCommands` и `deleteMyCommands` работают с default-списком команд конкретного бота.

Ограничения:

- command scopes не разделяются;
- language-specific команды не разделяются;
- команды показываются в чате как компактный select рядом с полем ввода;
- команды в истории сообщений кликабельны, если текст похож на Telegram bot command.

## Webhook delivery

Webhook-доставка делает одну попытку на новый update. Результат сохраняется в `delivery_attempts` и виден в UI.

Ограничения:

- production-grade automatic retry/backoff пока нет; доступен только ручной single retry и синхронный batch retry для локальной разработки;
- failed delivery можно повторить вручную из inspector последнего update;
- timeout задается `WEBHOOK_TIMEOUT_MS` как начальное значение и может быть переопределен через UI на панели `/`;
- допустимый UI-диапазон timeout: `1000`–`60000` мс;
- webhook URL для Docker Compose обычно должен использовать service DNS, например `http://bot:3000/webhook`, а не `localhost`.

## Long Polling

`getUpdates` поддерживает `offset`, `limit`, `timeout` и `allowed_updates`.

Ограничения:

- очередь updates хранится отдельно для каждого бота;
- при активном webhook `getUpdates` возвращает Telegram-like HTTP 409 conflict;
- `timeout` ограничен коротким ожиданием до 3 секунд, чтобы не блокировать single-process встроенный PHP server надолго;
- точная production-семантика долгого блокирующего ожидания пока не реализована.

## Групповые чаты

Групповой чат в текущей модели представлен записью `chats` и membership-связями `chat_members`, которые синхронизируются из profiles. Для совместимости import/export и UI-форм несколько profiles по-прежнему могут иметь одинаковый `chat_id` и `chat_type=group` или `chat_type=supergroup`. В `/chat` выбранный пользователь является отправителем сообщения, а история для group/supergroup читается по `bot_id + chat_id`.

Ограничения:

- отдельного UI управления membership пока нет;
- title группы пока генерируется как `Chat <chat_id>`;
- membership, роли, администраторы и service messages пока не моделируются;
- import разрешает общий `chat_id` только для group/supergroup profiles.

## Несколько ботов в одном экране

Отдельный multi-bot экран сейчас не реализуется. Текущая альтернатива — открыть `/chat?profile_id=<ID>&bot_id=<ID>` для каждого бота в отдельной вкладке. Это сохраняет однозначность очередей, webhook attempts и inspector для конкретного бота.

## Локальные данные и безопасность

Примеры должны использовать fake token, например `123456:local-dev-token`. Не используйте реальные production token и реальные пользовательские данные Telegram.

Логи и SQLite-данные являются локальными runtime-данными разработки. Inspector маскирует секреты в HTML-выводе, но сами файлы логов в `LOG_DIR` не являются security boundary.

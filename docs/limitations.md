# Ограничения эмулятора

Эмулятор предназначен для локальной разработки Telegram-ботов в Docker Compose. Он не подключается к настоящему Telegram, не принимает production traffic и не заменяет end-to-end проверки против настоящей Telegram-платформы.

## Поддерживаемая поверхность Bot API

Сейчас эмулятор поддерживает только эти Bot API endpoints:

- `GET|POST /bot<TOKEN>/getMe`
- `GET|POST /bot<TOKEN>/getUpdates`
- `POST /bot<TOKEN>/sendMessage`
- `POST /bot<TOKEN>/sendPhoto`
- `POST /bot<TOKEN>/sendDocument`
- `POST /bot<TOKEN>/sendVideo`
- `POST /bot<TOKEN>/sendAnimation`
- `POST /bot<TOKEN>/sendAudio`
- `POST /bot<TOKEN>/sendVoice`
- `POST /bot<TOKEN>/sendVideoNote`
- `POST /bot<TOKEN>/sendSticker`
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
- файловые upload и download: multipart file parts, `getFile`, file URL и хранение бинарных файлов;
- media-методы за пределами базовых `sendPhoto`, `sendDocument`, `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote`, `sendSticker`, `sendLocation`, `sendVenue`, `sendContact` и `sendDice`;
- payments, invoices, shipping/pre-checkout queries;
- Telegram Passport;
- games;
- forum topics, business connections и другие специальные режимы платформы;
- inline mode через `inline_query`;
- membership/admin workflows вроде `getChatMember`, `banChatMember`, `restrictChatMember`;
- production-grade webhook retry/backoff и фоновые delivery workers.

Это не полный каталог всех методов Telegram Bot API. Практическое правило проще: все, что не перечислено в разделе поддерживаемой поверхности, сейчас не поддерживается.

## Media upload

`sendPhoto`, `sendDocument`, `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote` и `sendSticker` принимают строковое или URL значение соответствующего media-поля, optional caption там, где он есть в Bot API, и optional `reply_markup`. Эмулятор сохраняет media placeholder в истории чата и возвращает Telegram-like `Message.*`.

Файловая загрузка через multipart пока не реализована. Для `setWebhook` и других POST-методов текстовые поля `multipart/form-data` поддерживаются, но файловые части не превращаются в Telegram file object.

## Structured сообщения

`sendLocation`, `sendVenue`, `sendContact` и `sendDice` принимают обязательные параметры Telegram Bot API, сохраняют structured payload в истории чата и возвращают Telegram-like `Message.location`, `Message.venue`, `Message.contact` или `Message.dice`.

UI чата может отправлять от пользователя photo/document по URL или file_id, location и contact. Эти сообщения создают обычный `message` update для webhook и Long Polling.

Ограничения:

- `sendDice` возвращает детерминированное значение: `4` для обычных dice emoji и `32` для slot machine, чтобы локальные тесты были стабильными;
- интерактивное голосование, карты и внешние previews пока не моделируются;
- multipart upload и локальное media-хранилище выделены в отдельную будущую задачу.

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

- автоматических retry/backoff пока нет;
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

Групповой чат в текущей модели представлен несколькими profiles с одинаковым `chat_id` и `chat_type=group` или `chat_type=supergroup`. В `/chat` выбранный пользователь является отправителем сообщения, а история для group/supergroup читается по `bot_id + chat_id`.

Ограничения:

- отдельной таблицы `groups` пока нет;
- title группы генерируется как `Chat <chat_id>`;
- membership, роли, администраторы и service messages пока не моделируются;
- import разрешает общий `chat_id` только для group/supergroup profiles.

## Несколько ботов в одном экране

Отдельный multi-bot экран сейчас не реализуется. Текущая альтернатива — открыть `/chat?profile_id=<ID>&bot_id=<ID>` для каждого бота в отдельной вкладке. Это сохраняет однозначность очередей, webhook attempts и inspector для конкретного бота.

## Локальные данные и безопасность

Примеры должны использовать fake token, например `123456:local-dev-token`. Не используйте реальные production token и реальные пользовательские данные Telegram.

Логи и SQLite-данные являются локальными runtime-данными разработки. Inspector маскирует секреты в HTML-выводе, но сами файлы логов в `LOG_DIR` не являются security boundary.

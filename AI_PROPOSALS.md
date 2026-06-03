# Предложения по модернизации проекта

Файл создан после прохода по очереди задач: активных `.aitasks` нет. Предложения ниже не являются обязательным планом работ; это список улучшений, которые стоит превращать в отдельные задачи по мере приоритета.

## Высокий приоритет

### 1. Разделить `Application` на контроллеры и сервисы

Сейчас `src/Application.php` одновременно отвечает за routing, parsing request body, Bot API, чат, webhook delivery, генерацию response payload и работу с несколькими репозиториями. Файл уже стал основным местом риска при каждом расширении Bot API.

Предлагаемый шаг:

- вынести Bot API методы в `BotApiController`;
- вынести UI chat handlers в `ChatController`;
- вынести webhook-доставку в `WebhookDeliveryService`;
- оставить `Application` как front router и composition root.

Ожидаемый эффект: проще добавлять `editMessageText`, media methods, retry webhook и групповые чаты без случайных регрессий в соседних маршрутах.

### 2. Ввести отдельный парсер Bot API request parameters

Сейчас parsing JSON, form-urlencoded и multipart находится в `Application`, а нормализация параметров размазана по методам. Это уже привело к ошибке с `multipart/form-data`.

Предлагаемый шаг:

- создать `BotApiRequestParser`;
- покрыть unit/integration тестами JSON, form-urlencoded, multipart text fields, пустое тело и malformed JSON;
- явно документировать, что файловые multipart-части пока игнорируются.

Ожидаемый эффект: меньше скрытых расхождений с bot frameworks, которые часто отправляют параметры разными content type.

### 3. Добавить `editMessageText`

`editMessageText` указан в roadmap и часто используется вместе с inline keyboard. После появления callback query это следующий естественный метод совместимости.

Предлагаемый scope первой версии:

- принимать `chat_id`, `message_id`, `text`, optional `reply_markup`;
- менять сохраненное bot-сообщение в `messages`;
- возвращать Telegram-like `Message`;
- возвращать 400 при неизвестном сообщении или попытке редактировать пользовательское сообщение;
- добавить тесты на JSON и form-urlencoded.

### 4. Добавить отдельный экран updates/delivery attempts

Сейчас чат показывает только последний update и последнюю delivery attempt. Для отладки webhook это быстро станет недостаточным.

Предлагаемый шаг:

- `/updates?bot_id=...` со списком updates, фильтром по `queue_state`, ссылкой на raw payload;
- `/delivery-attempts?bot_id=...` со статусом, URL, duration, error, request/response body;
- кнопка ручного resend для failed webhook delivery.

## Средний приоритет

### 5. Улучшить модель reply markup

Сейчас `reply_markup` хранится в `messages.raw_payload`. Это работает для MVP, но усложнит редактирование сообщений, повторный render и будущие типы кнопок.

Варианты:

- оставить JSON в `raw_payload`, но добавить helper `MessagePayload` для чтения/записи;
- или добавить отдельное поле `reply_markup` в `messages`.

Первый вариант дешевле и достаточен до появления сложных media/caption flows.

### 6. Сделать тестовый runner более структурным

`tests/bot_api_test.php` уже полезен, но растет как один длинный сценарий.

Предлагаемый шаг:

- выделить `tests/TestHttpClient.php`, `tests/TestCase.php`, fixtures;
- разделить сценарии: `bot_api_methods_test.php`, `updates_test.php`, `chat_ui_test.php`;
- сохранить запуск без Composer, если не хочется добавлять зависимость PHPUnit.

### 7. Актуализировать ROADMAP автоматически или чеклистом

README уже отражает тесты и текущий Bot API лучше, чем ROADMAP. Чтобы roadmap не отставал, стоит после каждой задачи из `.aitasks` явно проверять разделы:

- `Bot API surface`;
- текущий статус этапов;
- ограничения и следующие методы.

### 8. Добавить проверку HTML UI на Playwright или минимальный DOM-parser

Текущие UI-проверки ищут строки в HTML. Этого хватает для smoke tests, но плохо ловит структуру форм и вложенность.

Варианты:

- без новых зависимостей: PHP `DOMDocument` для проверки форм/кнопок;
- полноценнее: Playwright через отдельный Node-based тестовый контейнер.

## Низкий приоритет

### 9. Поддержать импорт/экспорт ботов и пользователей

Roadmap уже содержит import/export. Это удобно для повторяемых сценариев тестирования конкретного бота.

Минимальный scope:

- JSON export всех bots/profiles без истории;
- import с проверкой конфликтов token, user_id и chat_id;
- тесты на round-trip.

### 10. Улучшить групповые чаты

Сейчас групповой сценарий описан как будущий: несколько пользователей с одним `chat_id` без отдельной сущности группы. Для локального тестирования команд и callback в группах нужна явная модель.

Предлагаемый первый шаг:

- добавить `chats` или `groups`;
- разрешить нескольким profiles быть участниками одного chat;
- в UI выбирать отправителя внутри group chat;
- генерировать `message.chat.type=group|supergroup`.

### 11. Добавить примеры интеграции bot frameworks

README пока содержит общий Docker Compose пример. Для снижения трения стоит добавить короткие примеры:

- PHP SDK;
- python-telegram-bot или aiogram;
- grammY или Telegraf.

Важно: примеры должны использовать fake token и service DNS `http://telegram-emulator:8080`, без настоящего Telegram.

## Ближайшая практичная задача

Если выбирать одну следующую задачу, наиболее полезная: `editMessageText` с тестами. Она логически продолжает уже реализованные inline-кнопки и callback query, закрывает частый Telegram Bot API сценарий и хорошо проверит, насколько текущая модель сообщений готова к редактированию.

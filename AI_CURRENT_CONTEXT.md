# Текущий контекст проекта

## Последнее обновление

2026-06-05: по запросу пользователя про поддержку картинок, документов, гео и других типов сообщений создана очередь `.aitasks/task01`–`task07`. Выполнена первая задача: добавлены structured Bot API методы без файлового upload — `sendLocation`, `sendVenue`, `sendContact`, `sendDice`. Методы сохраняют structured payload в `messages.raw_payload`, возвращают Telegram-like `Message.location`/`Message.venue`/`Message.contact`/`Message.dice` и отображаются в `/chat` как компактные блоки. `sendDice` сделан детерминированным для стабильных тестов: `4` для обычных dice emoji, `32` для slot machine. Обновлены README, `docs/technical-spec.md`, `docs/limitations.md`, `ROADMAP.md` и HTTP-сценарии.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l src/BotApiController.php && php -l templates/chat/index.php && php -l tests/scenarios/http_scenarios.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-05: исправлена автопрокрутка истории сообщений `/chat`. Layout теперь перед HTMX swap запоминает, был ли `#chat-messages` возле нижнего края, и текущий `scrollTop`. После swap история прокручивается вниз только если пользователь был внизу; если пользователь читал старые сообщения выше, прежняя позиция восстанавливается и polling не сбрасывает просмотр вниз. В `tests/scenarios/http_scenarios.php` добавлены проверки на `htmx:beforeSwap`, `isChatMessagesNearBottom`, `shouldStickChatMessagesToBottom` и сохранение `previousChatMessagesScrollTop`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l templates/layout.php && php -l tests/scenarios/http_scenarios.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.

2026-06-05: увеличена видимая высота блока истории сообщений `/chat`: `#chat-messages` теперь имеет `max-height: 750px` вместо `500px`, чтобы на небольшом экране было видно больше переписки. В `tests/scenarios/http_scenarios.php` добавлена regression-проверка новой высоты.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l templates/chat/index.php && php -l tests/scenarios/http_scenarios.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.

2026-06-04: уточнен breakpoint компактной compose-зоны `/chat`. Вертикальная раскладка reply keyboard + textarea теперь включается только при `max-width: 560px`, чтобы на небольшом экране ноутбука блоки оставались в одну строку. В `tests/scenarios/http_scenarios.php` добавлена проверка, что `720px` не используется для этой зоны.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l templates/layout.php && php -l tests/scenarios/http_scenarios.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.

2026-06-04: сделана компактная нижняя зона `/chat` по просьбе пользователя для маленького экрана/телефона. Reply keyboard и textarea объединены в `chat-compose`: на широком экране reply-кнопки занимают левую узкую колонку меньше трети, поле ввода — правую широкую; на экранах до 560px блоки складываются вертикально. Если reply keyboard нет, textarea занимает всю ширину через `chat-compose-single`. Команды бота перенесены в раскрывающийся `details.bot-command-picker`, чтобы `/start` и другие команды не занимали место постоянно. Обновлены DOM-проверки в `tests/scenarios/http_scenarios.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l templates/chat/index.php && php -l templates/layout.php && php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.

2026-06-04: исправлена компоновка и автообновление страницы `/chat` после ручной проверки пользователя. `/chat/fragment` теперь возвращает только историю сообщений и reply keyboard; `Последний Update (inspector)`, `Raw payload (JSON)`, `Webhook delivery`, resend-кнопка, select команд и textarea больше не попадают в polling-обновление. На полной странице порядок стал практичнее: информация о паре пользователь-бот, live-блок сообщений/reply-клавиатуры, выбор команд, поле ввода, затем нижний диагностический inspector. Для текста сообщений заменен `white-space: pre-wrap` на `pre-line`, чтобы сохранять переносы строк без раздувания блоков лишними пробелами. В `tests/scenarios/http_scenarios.php` добавлены regression checks на порядок блоков, узкий HTMX-фрагмент и отсутствие `pre-wrap`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php -l templates/chat/index.php && php -l tests/scenarios/http_scenarios.php && find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.

2026-06-04: выполнена `.aitasks/task01.md` — исправлен polling вкладки "Чат". Раньше HTMX обновлял контейнер, внутри которого были select команд и textarea, поэтому поле ввода стиралось каждые 3 секунды. В `templates/chat/index.php` polling перенесен на `#chat-live`: `/chat/fragment` теперь возвращает историю/статусы/inspector и reply keyboard, но не textarea и не select команд. В `tests/scenarios/http_scenarios.php` добавлены regression checks, что fragment не содержит `<textarea` и `bot-command-select`, но содержит `#chat-messages`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: после пустой очереди `.aitasks` обновлен `AI_PROPOSALS.md`. Старые уже выполненные предложения удалены из будущего плана; добавлены актуальные направления: дальнейшая декомпозиция `Application`, разбиение `BotApiController` на method/payload services, базовый `sendVideo`, webhook retry/backoff, отдельная модель group chat, fixture import/export, дальнейшее дробление HTTP scenarios, Long Polling timeout, command scopes/language-specific commands, улучшение inspector и machine-readable Bot API surface. Ближайшая практичная задача предложена как базовый `sendVideo`.

Проверки:

- `Select-String` по `AI_PROPOSALS.md` — актуальные разделы и ближайшая задача присутствуют.
- `git diff --check` — whitespace-ошибок нет, только стандартные CRLF warnings Git на Windows.

2026-06-04: выполнена `.aitasks/task26.md` — выбран подход PHP `DOMDocument`/`DOMXPath` для структурных UI-проверок без Playwright и browser dependencies. В `tests/support/test_helpers.php` добавлены DOM helpers, в `tests/scenarios/http_scenarios.php` добавлена структурная проверка формы `/chat/send`, inline callback формы `/chat/callback`, reply keyboard button и select команд бота. README и `docs/adr-testing.md` обновлены.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task25.md` — добавлен `docs/roadmap-update-checklist.md` с правилом актуализации `ROADMAP.md` после задач, которые меняют scope, архитектуру, Bot API surface, ограничения или ближайшие планы. Checklist включает Bot API endpoints/ошибки, статусы этапов, ограничения, следующие методы, архитектуру и документацию запуска. В `AGENTS.md` добавлено постоянное правило пользоваться этим checklist; README и `AI_PROJECT_MAP.md` обновлены. `ROADMAP.md` не менялся, потому что задача добавляет правило актуализации, а не новый продуктовый пункт.

Проверки:

- `Select-String` по `docs/roadmap-update-checklist.md`, `AGENTS.md`, `README.md`, `AI_PROJECT_MAP.md` — ссылки и обязательные пункты присутствуют.
- `git diff --check` — whitespace-ошибок нет, только стандартные CRLF warnings Git на Windows.

2026-06-04: выполнена `.aitasks/task24.md` — структурирован тестовый runner. `tests/bot_api_test.php` оставлен entrypoint, assertions/HTTP helpers/form/multipart helpers/runtime server utilities вынесены в `tests/support/test_helpers.php`, unit-проверки `UpdateGenerator` — в `tests/scenarios/unit_scenarios.php`, HTTP-сценарии UI/Bot API/webhook/Long Polling/import-export — в `tests/scenarios/http_scenarios.php`. Запуск `php tests/bot_api_test.php` сохранен. Обновлены README, `AI_PROJECT_MAP.md` и `docs/adr-testing.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php && php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task23.md` — добавлен `src/ReplyMarkup.php` как общий helper для Bot API `reply_markup` поверх текущего `messages.raw_payload`. `BotApiController` использует helper для чтения Bot API параметра и кодирования raw payload, `UpdateGenerator` и `templates/chat/index.php` читают markup через helper, а UI вычисляет актуальную reply keyboard через `ReplyMarkup::latestKeyboard()`. Добавлен `tests/reply_markup_test.php`, обновлены README, `AI_PROJECT_MAP.md` и `docs/adr-testing.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/reply_markup_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task22.md` — parsing request body вынесен из `Application` в `src/BotApiRequestParser.php`. Parser принимает method/raw body/content-type и возвращает параметры для JSON, `application/x-www-form-urlencoded` и текстовых multipart fields; пустое тело и malformed JSON не меняют `$_POST`. `Application` применяет parser до маршрутизации, чтобы сохранить работу UI POST и Bot API POST при `enable_post_data_reading=Off`. Добавлен `tests/request_parser_test.php`, обновлены README, `AI_PROJECT_MAP.md`, `docs/adr-routing.md`, `docs/adr-testing.md` и `docs/technical-spec.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/request_parser_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task21.2.md` — Chat UI handlers вынесены из `Application` в `src/ChatController.php`. Новый контроллер обрабатывает `/chat`, `/chat/fragment`, `/chat/send`, `/chat/callback`, `/chat/clear`, формирует данные для `templates/chat/index.php`, создает message/callback updates и запускает webhook delivery для chat-сценариев. `Application` остался composition root/router; обновлены `public/index.php`, `AI_PROJECT_MAP.md`, `docs/adr-routing.md` и `docs/technical-spec.md`. Новые тесты не добавлялись, потому что поведение не расширялось; существующий Docker HTTP smoke runner покрывает chat-send/update/Bot API цепочку.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task21.1.md` — Bot API handlers вынесены из `Application` в `src/BotApiController.php`. Новый контроллер обрабатывает локальные маршруты `/bot<TOKEN>/<METHOD>` для `getMe`, `getUpdates`, `sendMessage`, `sendPhoto`, `sendDocument`, `editMessageText`, webhook commands, bot commands и `answerCallbackQuery`; `Application` остался composition root/router и делегирует Bot API requests. Удалены дублирующие Bot API helpers из `Application`, обновлены `public/index.php`, `AI_PROJECT_MAP.md`, `docs/adr-routing.md` и `docs/technical-spec.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена первая безопасная часть `.aitasks/task21.md` — webhook delivery вынесена из `Application` в `src/WebhookDeliveryService.php`. Сервис выполняет одну попытку POST-доставки update, сохраняет `delivery_attempts` и обновляет состояние update через `UpdateRepository`. `Application` остался router/composition root и делегирует доставку сервису с текущим timeout. Полная декомпозиция Bot API и Chat UI разбита на `.aitasks/task21.1.md` и `.aitasks/task21.2.md`, чтобы не делать рискованный большой refactor одним патчем. Обновлены `AI_PROJECT_MAP.md` и `docs/technical-spec.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task20.md` — принята стратегия тестирования. Создан `docs/adr-testing.md`: текущий самописный Docker HTTP smoke runner `tests/bot_api_test.php` остается основным контуром, PHPUnit не добавляется без явной необходимости, а при росте тестов сначала выделяются helpers/scenarios с сохранением одного entrypoint. README, technical spec, ROADMAP и project map обновлены.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по документации — `bot_api_test.php`, PHPUnit, Docker HTTP smoke runner и `docs/adr-testing.md` отражены.

2026-06-04: выполнена `.aitasks/task19.md` — принято решение по micro-framework. Создан `docs/adr-routing.md`: текущий custom router в `src/Application.php` остается, Slim/Symfony components не внедряются без явной необходимости, а первичная модернизация должна декомпозировать `Application` на parser/handlers/services. В README, technical spec, ROADMAP и project map добавлены ссылки/резюме.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по документации — custom router, Slim, Symfony components, Docker-first и критерии пересмотра отражены.

2026-06-04: выполнена `.aitasks/task18.md` — принято решение по режиму нескольких ботов в одном экране. Multi-bot экран не входит в текущий scope: основной сценарий остается парой `profile_id`/`bot_id`, а текущая альтернатива — открывать одного пользователя с разными ботами в разных вкладках. Решение и причины задокументированы в `docs/technical-spec.md`, `README.md`, `docs/limitations.md` и `ROADMAP.md`.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по документации — решение по multi-bot UI и альтернатива через вкладки отражены.

2026-06-04: выполнена `.aitasks/task17.md` — спроектирована и начата реализация group chat модели. Принято решение не вводить отдельную таблицу `groups` на первом шаге: group/supergroup моделируются несколькими `profiles` с общим `chat_id`, а выбранный profile в `/chat` является отправителем. Для group/supergroup история читается по `bot_id + chat_id`; import разрешает общий `chat_id` только для group/supergroup profiles и сохраняет конфликт для private/channel. `UpdateGenerator` и Bot API ответы бота формируют group-like `Chat` с `type=group|supergroup` и `title`. UI выбора пользователя переименован в `Пользователь / отправитель` и показывает `chat_type`/`chat_id`. Обновлены README, technical spec, limitations, roadmap и project map.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.

2026-06-04: выполнена `.aitasks/task16.md` — принят и задокументирован формат import/export. В `docs/technical-spec.md` добавлен раздел `4.7 Import/export формат`: JSON envelope v1 с `version`, `exported_at`, массивами `bots`/`profiles`, предпочтительной envelope-формой импорта, допустимой bare array-формой, стратегией конфликтов HTTP 409 для `token`, `user_id`, `chat_id`, правилом validate-before-write и стратегией расширения будущими top-level массивами. `ROADMAP.md` обновлен: вопрос формата import/export больше не открыт. В `README.md` добавлена ссылка на описание формата в technical spec.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по `docs/technical-spec.md`, `README.md`, `ROADMAP.md` — endpoints, `version`, `exported_at`, HTTP 409 и принятое решение отражены.

2026-06-04: выполнена `.aitasks/task15.md` — добавлено явное описание ограничений эмулятора. Создан `docs/limitations.md` со списком поддерживаемых Bot API endpoints, правилом HTTP 501 для неподдерживаемых методов, ограничениями media upload/download, command scopes, language-specific commands, webhook retries/timeout, Long Polling timeout и напоминанием, что эмулятор не подключается к настоящему Telegram. В `README.md`, `docs/technical-spec.md`, `ROADMAP.md` и `AI_PROJECT_MAP.md` добавлены ссылки и синхронизированы краткие формулировки.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по документации — fake token, HTTP 501, отсутствие подключения к настоящему Telegram и ограничения upload/retry/timeout/commands описаны.

2026-06-04: выполнена `.aitasks/task13.2.md` — улучшено поведение чата после HTMX-обновлений. История сообщений получила стабильный контейнер `#chat-messages` с маркером `data-chat-messages`, а layout прокручивает историю вниз при `DOMContentLoaded` и после `htmx:afterSwap`. Блок команд бота убран из верхней отдельной панели и перенесен вниз к форме ввода как компактный `select`, который отправляет выбранную команду через `onchange` без отдельной кнопки. Reply keyboard тоже остается в нижней зоне инструментов перед вводом.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-04: выполнена `.aitasks/task14.md` — добавлены примеры интеграции bot frameworks. Создан `docs/framework-examples.md` с примерами для PHP HTTP-клиента, python-telegram-bot, aiogram, grammY и Telegraf; все примеры используют fake token `123456:local-dev-token` и Docker service DNS `http://telegram-emulator:8080`. В `README.md` добавлена ссылка на документ, `AI_PROJECT_MAP.md` обновлен.

Проверки:

- `git diff --check` — whitespace-ошибок нет, только стандартные предупреждения Git о CRLF на Windows.
- `Select-String` по `docs/framework-examples.md`, `README.md`, `AI_PROJECT_MAP.md` — fake token, service DNS и ссылки на документацию присутствуют.

2026-06-03: выполнена `.aitasks/task13.md` — расширена документация Docker Compose сценариев. В `README.md` добавлен раздел с compose-фрагментами для Long Polling и webhook ботов, fake token `123456:local-dev-token`, service DNS (`telegram-emulator`, `bot`), пояснение `localhost` внутри контейнера и варианты `TELEGRAM_API_BASE_URL`.

Проверки:

- Просмотрен diff README и поиск по token/base URL: примеры используют fake token и Docker service DNS.

2026-06-03: выполнена `.aitasks/task12.md` — добавлен базовый Bot API `POST /bot<TOKEN>/sendDocument`. Метод принимает JSON/form-urlencoded/multipart text fields, требует `chat_id` и строковый/URL `document`, поддерживает optional `caption` и `reply_markup`, сохраняет document metadata в `raw_payload`, возвращает Telegram-like `Message.document` и показывает document placeholder с caption в чате. Файловые upload пока не поддерживаются. Обновлены `README.md`, `docs/technical-spec.md`, `ROADMAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task11.md` — добавлен базовый Bot API `POST /bot<TOKEN>/sendPhoto`. Метод принимает JSON/form-urlencoded/multipart text fields, требует `chat_id` и строковый/URL `photo`, поддерживает optional `caption` и `reply_markup`, сохраняет photo metadata в `raw_payload`, возвращает Telegram-like `Message.photo` и показывает photo placeholder с caption в чате. Файловые upload пока не поддерживаются. Обновлены `README.md`, `docs/technical-spec.md`, `ROADMAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task10.md` — реализован Bot API `POST /bot<TOKEN>/editMessageText`. Метод принимает JSON/form-urlencoded, требует `chat_id`, `message_id`, `text`, поддерживает optional `reply_markup`, редактирует только сообщения бота и возвращает Telegram-like `Message`. Ошибки неизвестного чата или сообщения возвращают Telegram-like HTTP 400. Обновлены `README.md`, `docs/technical-spec.md`, `ROADMAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task09.md` — добавлена безопасная очистка истории. `POST /chat/clear` очищает messages и updates только выбранной пары `profile_id`/`bot_id`, требует `confirm_clear=1` и доступен из чата через кнопку с browser confirm. `POST /updates/clear` удаляет pending/confirmed updates выбранного бота, не трогая delivered/failed и updates других ботов; доступен на `/updates?bot_id=<id>` с явным подтверждением. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

2026-06-03: выполнена `.aitasks/task08.md` — добавлен `/import-export` и JSON endpoints `/export/bots`, `/export/profiles`, `/import/bots`, `/import/profiles`. Экспорт включает только bots/profiles без messages, updates и delivery attempts. Импорт валидирует payload до записи и отклоняет конфликты `token`, `user_id`, `chat_id`; UI содержит ссылки export и textarea-формы import. Обновлены `README.md`, `ROADMAP.md`, `AI_PROJECT_MAP.md` и `tests/bot_api_test.php`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find public src tests templates -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.

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
- Bot API: `POST /bot<TOKEN>/sendPhoto` принимает строковый/URL `photo`, optional `caption`/`reply_markup`, возвращает Telegram-like `Message.photo`; файловые upload пока не поддерживаются.
- Bot API: `POST /bot<TOKEN>/sendDocument` принимает строковый/URL `document`, optional `caption`/`reply_markup`, возвращает Telegram-like `Message.document`; файловые upload пока не поддерживаются.
- Bot API: `POST /bot<TOKEN>/editMessageText` редактирует только сообщения бота по `chat_id`/`message_id`, поддерживает optional `reply_markup` и возвращает Telegram-like `Message`.
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
- `/import-export` экспортирует и импортирует JSON для bots/profiles без истории; импорт отклоняет конфликты `token`, `user_id`, `chat_id`.
- Чат умеет очищать историю и updates выбранного диалога; `/updates` умеет очищать pending/confirmed updates выбранного бота.
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

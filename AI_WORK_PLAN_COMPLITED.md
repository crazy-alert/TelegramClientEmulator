# Завершенные планы работы

## `getFile` и локальная отдача media (2026-06-05)

- [x] Проверить текущий `MediaStorage`, routing и Bot API routes.
- [x] Добавить lookup по `local-media:<sha256>` и безопасное разрешение `file_path`.
- [x] Реализовать `GET|POST /bot<TOKEN>/getFile`.
- [x] Реализовать локальную отдачу `GET /file/bot<TOKEN>/<file_path>` с проверкой token и path traversal.
- [x] Добавить HTTP tests для success, unknown `file_id`, unknown token и path traversal.
- [x] Обновить README, technical spec, limitations и ROADMAP.
- [x] Запустить проверки, обновить контекст, перенести чеклист, удалить task06, сделать коммит и push.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php`
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php`
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"`

## Multipart upload и локальное media-хранилище (2026-06-05)

- [x] Проверить текущий multipart parsing, media payload и UI-формы.
- [x] Добавить локальное media-хранилище с настраиваемым путем, лимитом размера и безопасными именами.
- [x] Поддержать multipart upload для `sendPhoto` и `sendDocument`, сохранив строковые `file_id`/URL.
- [x] Добавить attach file в UI для пользовательских фото и документов.
- [x] Добавить тесты parser/Bot API/UI upload и проверить сценарии.
- [x] Обновить README, technical spec, limitations и ROADMAP.
- [x] Обновить `AI_CURRENT_CONTEXT.md`, перенести чеклист в `AI_WORK_PLAN_COMPLITED.md`, удалить task05 и сделать коммит.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/request_parser_test.php`
- `docker compose run --rm --no-deps telegram-emulator php tests/reply_markup_test.php`
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"`

## Poll/quiz сообщения (2026-06-05)

- [x] Добавить Bot API route `sendPoll`, parsing/validation `question` и `options`, Telegram-like `Message.poll`.
- [x] Обновить renderer `/chat` для read-only poll блока.
- [x] Добавить HTTP tests на success, invalid options, required params и UI rendering.
- [x] Обновить README/spec/limitations/ROADMAP.
- [x] Запустить проверки, обновить контекст, удалить task04, сделать коммит и push.

## Audio/video/voice/sticker Bot API методы (2026-06-05)

- [x] Добавить Bot API routes и payload generation для `sendVideo`, `sendAnimation`, `sendAudio`, `sendVoice`, `sendVideoNote`, `sendSticker`.
- [x] Обновить рендер `/chat` для новых media placeholders.
- [x] Расширить HTTP tests на success, required param, chat not found и UI rendering.
- [x] Обновить README/spec/limitations/ROADMAP.
- [x] Запустить проверки, обновить контекст, удалить task03, сделать коммит и push.

## Прием structured сообщений от пользователя в UI (2026-06-05)

- [x] Обновить `ChatController` и `UpdateGenerator` для user structured payload без multipart upload.
- [x] Добавить компактный UI-блок в `/chat` для отправки photo/document/location/contact.
- [x] Расширить HTTP/DOM тесты на update payload и rendering.
- [x] Обновить документацию по измененной UI/Bot API surface.
- [x] Запустить проверки, обновить контекст, удалить task02, сделать коммит и push.

## Structured Bot API сообщения без файлов (2026-06-05)

- [x] Сформировать `.aitasks` с поэтапной поддержкой media/geo/contact/dice/poll/file upload.
- [x] Прочитать первую задачу и реализовать минимальный проверяемый слой.
- [x] Обновить тесты и документацию по измененной Bot API/UI surface.
- [x] Запустить проверки, обновить контекст, удалить выполненную задачу, сделать коммит и push.

## Условная автопрокрутка истории чата (2026-06-05)

- [x] Переписать JS автопрокрутки в `templates/layout.php` с проверкой положения скролла до HTMX swap.
- [x] Обновить regression checks для новой логики.
- [x] Запустить lint/test, обновить контекст, сделать коммит и push.

## Увеличить блок истории сообщений (2026-06-05)

- [x] Увеличить `max-height` блока `#chat-messages`.
- [x] Добавить regression check на новую высоту.
- [x] Запустить lint/test, обновить контекст, сделать коммит и push.

## Уточнить breakpoint compose-зоны чата (2026-06-04)

- [x] Уменьшить CSS breakpoint для `.chat-compose` до смартфонного диапазона.
- [x] Добавить regression check на breakpoint.
- [x] Запустить lint/тесты, обновить контекст и сделать коммит/push.

## Компактная нижняя зона чата (2026-06-04)

- [x] Обновить разметку `templates/chat/index.php` для компактной compose-зоны.
- [x] Добавить CSS для desktop/mobile поведения без ухудшения узких экранов.
- [x] Обновить HTTP/DOM regression checks.
- [x] Запустить lint и тесты.
- [x] Обновить контекст, архив плана, сделать коммит и push.

## Уточнить polling и порядок блоков чата (2026-06-04)

- [x] Проверить текущую разметку `templates/chat/index.php` после переноса live-фрагмента.
- [x] Обновить HTTP-сценарии, чтобы они проверяли узкий polling-фрагмент и порядок основных блоков.
- [x] Запустить PHP lint и тесты проекта.
- [x] Обновить `AI_CURRENT_CONTEXT.md`, перенести итог в `AI_WORK_PLAN_COMPLITED.md`, очистить план.
- [x] Сделать отдельный коммит и push.

## Исправить polling чата (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Найти причину: `hx-swap` обновлял контейнер вместе с textarea.
- [x] Перенести HTMX polling на live-блок без формы ввода.
- [x] Добавить regression checks для `/chat/fragment`.
- [x] Прогнать тесты и PHP lint, обновить контекст, удалить задачу, сделать коммит и push.

## Анализ кода и обновление AI_PROPOSALS (2026-06-04)

- [x] Проверить git status, пустую `.aitasks` и текущий `AI_PROPOSALS.md`.
- [x] Быстро оценить текущую структуру кода, тестов, ROADMAP и ограничений.
- [x] Переписать `AI_PROPOSALS.md`: убрать реализованные предложения, добавить актуальные.
- [x] Обновить контекст/архив плана, выполнить документальные проверки, сделать коммит и push.

## Проверка HTML UI через DOM-parser или Playwright (2026-06-04)

- [x] Проверить git status и текст задачи.
- [x] Проверить доступность DOMDocument/Playwright и выбрать подход без лишних зависимостей.
- [x] Добавить структурную проверку HTML чата.
- [x] Документировать запуск.
- [x] Прогнать проверки, обновить контекст, удалить задачу, сделать коммит и push.

## Чеклист актуализации ROADMAP (2026-06-04)

- [x] Проверить git status и текст задачи.
- [x] Проверить текущие правила AGENTS/документации по ROADMAP.
- [x] Добавить checklist актуализации ROADMAP с Bot API surface, статусами этапов, ограничениями и следующими методами.
- [x] Обновить карту проекта/контекст.
- [x] Прогнать документационные проверки, удалить задачу, сделать коммит и push.

## Структурировать тестовый runner (2026-06-04)

- [x] Проверить git status и текст задачи.
- [x] Изучить структуру текущего runner: helpers, HTTP client, сценарии, main.
- [x] Вынести helper/client/assertion utilities в support-файл.
- [x] Разделить сценарии по смыслу или подготовить явную структуру scenario-функций.
- [x] Обновить README/ADR/project map при необходимости.
- [x] Прогнать все тесты и lint, обновить контекст, удалить задачу, сделать коммит и push.

## Улучшить модель reply markup (2026-06-04)

- [x] Проверить git status и текст задачи.
- [x] Найти текущие места чтения/записи `reply_markup` и media raw payload.
- [x] Спроектировать минимальный helper/value object без изменения HTTP-поведения.
- [x] Подключить helper в Bot API и UI.
- [x] Добавить/расширить тесты на inline keyboard и reply keyboard.
- [x] Обновить документацию/контекст при необходимости, прогнать проверки, удалить задачу, сделать коммит и push.

## Отдельный parser Bot API request parameters (2026-06-04)

- [x] Проверить git status и текст задачи.
- [x] Изучить текущий parsing и тестовый стиль проекта.
- [x] Создать `BotApiRequestParser` и подключить его в `Application`.
- [x] Добавить focused parser tests на JSON, form-urlencoded, multipart, пустое тело и malformed JSON.
- [x] Обновить карту проекта/документацию при необходимости.
- [x] Прогнать parser tests, Bot API smoke test и PHP lint, обновить контекст, удалить задачу, сделать коммит и push.

## Вынести Chat UI handlers (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Изучить текущие chat routes, зависимости и helpers в `Application`.
- [x] Создать `ChatController` с handlers для chat UI routes.
- [x] Подключить controller в `Application` и `public/index.php`.
- [x] Обновить карту проекта/документацию.
- [x] Прогнать Bot API тесты и PHP lint, обновить контекст, удалить задачу, сделать коммит и push.

## Вынести Bot API handlers (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Изучить границы Bot API методов и helper-функций в `Application`.
- [x] Создать `BotApiController` с handlers и helpers.
- [x] Подключить controller в `Application` и `public/index.php`.
- [x] Обновить карту проекта/документацию.
- [x] Прогнать Bot API тесты и PHP lint, обновить контекст, удалить задачу, сделать коммит и push.

## Декомпозиция Application: webhook delivery (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Оценить размер задачи и выбрать безопасный первый срез.
- [x] Вынести webhook delivery в отдельный service.
- [x] Оценить вынос Bot API request parsing/handlers без ломки маршрутов.
- [x] Оценить вынос Chat UI handlers без изменения HTML/redirect поведения.
- [x] Обновить карту проекта и документацию по архитектуре.
- [x] Прогнать Bot API тесты и PHP lint, обновить контекст, удалить задачу или зафиксировать остаток подзадачами, сделать коммит и push.

## Стратегия тестов (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Проверить текущие тесты и документацию запуска.
- [x] Создать ADR/раздел со стратегией: текущий HTTP smoke runner, модульное разделение, PHPUnit.
- [x] Обновить README/technical spec/ROADMAP/project map.
- [x] Проверить документацию, обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Решение по micro-framework (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Найти текущие упоминания custom router/framework в документации.
- [x] Создать ADR с вариантами custom router, Slim, Symfony components и критерием пересмотра.
- [x] Обновить README/technical spec/ROADMAP/project map.
- [x] Проверить документацию, обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Режим нескольких ботов в одном экране (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Оценить текущую альтернативу: пары `profile_id`/`bot_id` в URL и работа в нескольких вкладках.
- [x] Задокументировать решение не реализовывать общий multi-bot экран в текущем scope.
- [x] Обновить README/technical spec/ROADMAP/limitations при необходимости.
- [x] Проверить документацию, обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Групповые чаты (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Проверить текущую модель `profiles.chat_id/chat_type`, генерацию update и UI чата.
- [x] Зафиксировать решение: отдельная таблица groups пока не нужна, группа моделируется общим `chat_id` у нескольких profiles.
- [x] Сделать историю group/supergroup общей по `bot_id + chat_id` и оставить выбор profile как выбор отправителя.
- [x] Разрешить import нескольких group/supergroup profiles с одним `chat_id`, сохранив конфликты для private.
- [x] Уточнить Telegram-like `message.chat` для group/supergroup и добавить тесты payload.
- [x] Обновить документацию, прогнать проверки, обновить контекст, удалить задачу, сделать коммит и push.

## Формат import/export (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Изучить текущую реализацию `/export/*` и `/import/*`.
- [x] Описать формат JSON, версионирование и стратегию конфликтов в технической документации.
- [x] Обновить README/ROADMAP/карту проекта при необходимости.
- [x] Проверить документацию, обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Описание ограничений эмулятора (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Найти текущие упоминания поддерживаемых методов и ограничений в README, technical spec и ROADMAP.
- [x] Создать документ с поддерживаемыми методами, неподдерживаемыми областями и текущими ограничениями.
- [x] Добавить ссылки/короткие резюме в README, `docs/technical-spec.md` и карту проекта.
- [x] Проверить документацию на fake token, отсутствие реального Telegram и непротиворечивость текущему коду.
- [x] Обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Компактные команды и автопрокрутка чата (2026-06-04)

- [x] Проверить git status, активный план и текст задачи.
- [x] Изучить шаблон чата, CSS/JS layout и существующие тесты.
- [x] Перенести UI команд к форме отправки и сделать select кликабельным без отдельной кнопки.
- [x] Добавить автопрокрутку истории вниз при первичной загрузке и HTMX-обновлениях.
- [x] Обновить тесты и документацию при необходимости.
- [x] Прогнать проверки, обновить контекст, архив планов, удалить задачу, сделать коммит и push.

## Примеры интеграции bot frameworks (2026-06-04)

- [x] Проверить состояние git, активный план и текущую задачу.
- [x] Проверить уже созданный `docs/framework-examples.md` и официальные формулировки base URL/api root.
- [x] Добавить ссылку на документ в `README.md` и `AI_PROJECT_MAP.md`.
- [x] Проверить документацию на fake token, Docker service DNS и отсутствие опасных примеров.
- [x] Обновить контекст, перенести чеклист в завершенные, удалить `.aitasks/task14.md`, сделать коммит и push.

## Документация Docker Compose сценариев (2026-06-03)

- [x] Расширить README compose-фрагментами для webhook и Long Polling.
- [x] Объяснить service DNS, `localhost` внутри контейнера и `TELEGRAM_API_BASE_URL`.
- [x] Проверить, что примеры используют fake token.
- [x] Обновить контекст, удалить задачу, сделать коммит и push.

## Базовый sendDocument (2026-06-03)

- [x] Добавить Bot API route `sendDocument` и валидацию параметров.
- [x] Сохранять document metadata и возвращать Telegram-like `Message.document`.
- [x] Показать document placeholder с caption в чате.
- [x] Обновить тесты и документацию по ограничениям upload.
- [x] Прогнать проверки, удалить задачу, обновить контекст, сделать коммит и push.

## Базовый sendPhoto (2026-06-03)

- [x] Добавить Bot API route `sendPhoto` и валидацию параметров.
- [x] Сохранять photo metadata в сообщении и возвращать Telegram-like `Message.photo`.
- [x] Показать photo placeholder с caption в чате.
- [x] Обновить тесты и документацию по ограничениям upload.
- [x] Прогнать проверки, удалить задачу, обновить контекст, сделать коммит и push.

## Реализовать editMessageText (2026-06-03)

- [x] Добавить поиск и обновление bot message по `chat_id`/`message_id`.
- [x] Добавить Bot API route и Telegram-like валидацию параметров.
- [x] Поддержать optional `reply_markup` и возврат Telegram-like `Message`.
- [x] Обновить тесты и документацию.
- [x] Прогнать проверки, удалить задачу, обновить контекст, сделать коммит и push.

## Очистка истории по пользователю или боту (2026-06-03)

- [x] Проверить repositories сообщений/updates, UI чата и `/updates`.
- [x] Добавить методы удаления выбранного диалога и pending/confirmed updates бота.
- [x] Добавить POST routes и UI-кнопки с подтверждением.
- [x] Добавить тесты на удаление только выбранной области данных.
- [x] Обновить документацию/контекст, прогнать проверки, удалить задачу, сделать коммит и push.

## Import/export ботов и пользователей (2026-06-03)

- [x] Проверить текущие формы, repositories и поля bots/profiles.
- [x] Добавить export/import endpoints с JSON-форматом без истории сообщений.
- [x] Добавить UI-экран и nav-ссылку.
- [x] Добавить тесты round-trip import/export и конфликтов.
- [x] Обновить документацию/контекст, прогнать проверки, удалить задачу, сделать коммит и push.

## Inspector request/response (2026-06-03)

- [x] Проверить формат `HttpLogger`, текущие delivery attempts и документацию.
- [x] Добавить read-only чтение Bot API событий из HTTP JSONL-логов с маскированием секретов.
- [x] Добавить route, nav-ссылку и шаблон request/response inspector.
- [x] Добавить тесты фильтра и маскирования token.
- [x] Обновить документацию/контекст, прогнать проверки, удалить задачу, сделать коммит и push.

## Вкладка updates в интерфейсе (2026-06-03)

- [x] Проверить `UpdateRepository`, routes, layout и экран delivery attempts.
- [x] Добавить выборку updates с context и фильтрами.
- [x] Добавить route `/updates`, nav-ссылку и шаблон списка.
- [x] Расширить тесты и документацию.
- [x] Прогнать проверки, удалить задачу, обновить контекст, сделать коммит и push.

## Inline validation форм (2026-06-03)

- [x] Проверить текущие формы ботов/пользователей и нормализацию repositories.
- [x] Добавить серверную валидацию UI-форм перед записью.
- [x] Показать ошибки рядом с полями в шаблонах.
- [x] Добавить тесты на основные ошибки форм.
- [x] Обновить документацию/контекст, прогнать проверки, удалить задачу, сделать коммит и push.

## HTMX polling для обновления чата (2026-06-03)

- [x] Проверить текущий рендер чата, layout и тестовый контур.
- [x] Выделить обновляемый фрагмент чата без потери выбранных `profile_id`/`bot_id`.
- [x] Добавить HTMX polling и маршрут partial-обновления.
- [x] Обновить тесты и документацию.
- [x] Прогнать проверки, удалить задачу из `.aitasks`, обновить контекст, сделать коммит и push.

## Настройки webhook timeout в UI (2026-06-03)

- [x] Проверить текущую реализацию timeout, dashboard и тестовый контур.
- [x] Добавить хранение и валидацию `webhook_timeout_ms` через SQLite `settings`.
- [x] Добавить форму на панели и использовать сохранённое значение при webhook-доставке.
- [x] Обновить документацию и карту проекта.
- [x] Добавить/обновить тесты для UI-настройки и диапазона.
- [x] Прогнать проверки, удалить задачу из `.aitasks`, обновить контекст и сделать коммит.

## Экран delivery attempts (2026-06-03)

- [x] Добавить выборку delivery attempts с фильтрами по боту/update.
- [x] Добавить маршрут и шаблон `/delivery-attempts`.
- [x] Добавить ссылку в навигацию и переход в чат из строк attempts.
- [x] Расширить тесты HTML smoke-проверкой списка и фильтра.
- [x] Обновить документацию/контекст, удалить `task02.md`, сделать коммит и push.

## Ручной resend failed webhook delivery (2026-06-03)

- [x] Изучить текущую webhook-доставку, repositories и UI inspector.
- [x] Добавить поиск update по id и маршрут `/updates/{id}/resend`.
- [x] Добавить кнопку resend в chat inspector только для failed webhook update.
- [x] Расширить тесты на failed resend и successful resend.
- [x] Обновить документацию/контекст, удалить `task01.md`, сделать коммит и push.

## Анализ после пустой очереди задач (2026-06-03)

- [x] Сверить README, ROADMAP и текущую кодовую базу.
- [x] Обновить ROADMAP, если он отстал от реализованного функционала.
- [x] Проанализировать архитектуру и тесты проекта.
- [x] Создать `AI_PROPOSALS.md` с приоритетными предложениями модернизации.
- [x] Прогнать проверки, обновить контекст, сделать коммит и push.

## Компактный рендер кликабельных команд (2026-06-03)

- [x] Исправить рендер inline-формы команды без шаблонных переносов внутри `pre-wrap`.
- [x] Добавить тест на компактный HTML команды.
- [x] Прогнать проверки, обновить контекст, сделать коммит и push.

## Кликабельные команды в истории чата (2026-06-03)

- [x] Проверить текущий рендер истории сообщений.
- [x] Добавить безопасный рендер текста с кликабельными bot commands.
- [x] Расширить интеграционный тест на кликабельную команду в истории.
- [x] Прогнать проверки, обновить контекст, сделать коммит и push.

## Команды и клавиатуры Bot API (2026-06-03)

- [x] Изучить текущие слои хранения сообщений и updates для минимальной реализации.
- [x] Добавить хранение команд бота и Bot API методы `setMyCommands`/`getMyCommands`/`deleteMyCommands`.
- [x] Расширить `sendMessage` на `reply_markup` и сохранить markup у сообщений бота.
- [x] Добавить UI команд, кликабельные команды, reply keyboard и inline keyboard.
- [x] Добавить callback/button updates и минимальный `answerCallbackQuery`.
- [x] Обновить тесты и документацию.
- [x] Прогнать проверки, обновить контекст, закрыть `.aitasks/task.txt`, сделать коммит и push.

## Тесты Bot API и multipart setWebhook (2026-06-03)

- [x] Изучить структуру проекта, текущие Bot API маршруты, генерацию payload и существующие тесты.
- [x] Спроектировать минимальное покрытие: реализованные методы, структуры `User`/`Chat`/`Message`/`Update`/`WebhookInfo`, ошибки и неподдерживаемые методы.
- [x] Добавить интеграционный тест `tests/bot_api_test.php`.
- [x] Исправить ручной парсинг `multipart/form-data` для `setWebhook` и других POST-методов.
- [x] Обновить `README.md` и `docs/technical-spec.md`.
- [x] Прогнать Bot API тесты и PHP lint в Docker.

## Этап 2: базовый чат и генерация Telegram-like Update (2026-05-26)

- [x] Спроектировать архитектуру Этапа 2 (расширяемость)
- [x] Создать файл задачи в .aitasks/
- [x] Обновить AI_WORK_PLAN.md с чеклистом реализации
- [x] План утверждён
- [x] Создать src/MessageRepository.php
- [x] Создать src/UpdateRepository.php
- [x] Создать src/UpdateGenerator.php
- [x] Изменить src/Application.php (маршруты, activeProfile/activeBot)
- [x] Изменить public/index.php (require)
- [x] Создать templates/chat/index.php
- [x] Изменить templates/layout.php (переключатели + ссылка «Чат»)
- [x] Проверить работоспособность (PHP lint — 11 файлов без ошибок)
- [x] Коммит bfec091 и пуш

## Проверка AGENTS.md и правки стиля (2026-05-26)

- [x] Перечитать AGENTS.md
- [x] Проверить git status --short --branch
- [x] Создать недостающие контекстные файлы и директории
- [x] Проверить итоговый набор файлов
- [x] Добавить PHPDoc на русском в BotRepository.php
- [x] Добавить PHPDoc на русском в ProfileRepository.php
- [x] Добавить PHPDoc на русском в public/index.php
- [x] Актуализировать AI_WORK_PLAN.md
- [x] Добавить правило K&R в AGENTS.md
- [x] Исправить все PHP-файлы на стиль K&R
- [x] Коммит 8e2f552 и пуш

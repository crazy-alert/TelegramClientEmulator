# ADR: HTTP routing и micro-framework

## Статус

Принято: оставить текущий custom router в `src/Application.php` и не внедрять micro-framework сейчас.

## Контекст

Проект остается Docker-first локальным эмулятором Telegram Bot API. Основной runtime — `php:8.3-cli-alpine` и встроенный PHP HTTP server. Сейчас маршруты покрывают UI, локальные Bot API endpoints, webhook delivery, Long Polling, import/export и inspector.

Текущая боль не в отсутствии framework, а в росте ответственности `Application`: маршрутизация, parsing request parameters, orchestration, validation и rendering находятся в одном классе. Это лучше решать постепенной декомпозицией на локальные сервисы, а не немедленной заменой HTTP слоя.

## Варианты

### Оставить custom router

Плюсы:

- нет новых зависимостей и Composer bootstrap;
- полностью совместимо с текущим Docker-first запуском;
- проще читать новым участникам проекта;
- тесты уже проверяют HTTP-поведение через реальный встроенный server;
- легко сохранять Telegram-like нестандартные детали маршрутов `/bot<TOKEN>/<METHOD>`.

Минусы:

- `Application` продолжит расти без дисциплины декомпозиции;
- нет готовых middleware, route groups и dependency injection;
- придется вручную поддерживать parsing, validation и error handling.

### Slim

Плюсы:

- легкий HTTP micro-framework;
- удобная маршрутизация и middleware;
- меньше boilerplate для route dispatch.

Минусы:

- потребуется Composer dependency и bootstrap;
- придется переносить текущие routes и тестовый запуск;
- выигрыш будет ограниченным, пока основная сложность находится в orchestration и storage, а не в dispatch.

### Symfony components

Плюсы:

- качественные компоненты HttpFoundation/Routing;
- можно подключать частями без полного Symfony app;
- хорошая база для роста проекта.

Минусы:

- больше зависимостей и соглашений;
- выше порог входа для маленького локального инструмента;
- миграция может отвлечь от Bot API совместимости и UI-сценариев.

## Решение

На текущем этапе оставить custom router. Приоритетная модернизация:

- выделить parser Bot API request parameters и handlers/services для Bot API methods в отдельный `BotApiController`;
- вынести UI actions из `Application` в небольшие контроллеры или action-классы;
- оставить HTTP smoke tests в Docker как защиту от regressions.

Первый шаг выполнен: локальные маршруты `/bot<TOKEN>/<METHOD>` и handlers методов `getMe`, `getUpdates`, `sendMessage`, `sendPhoto`, `sendDocument`, `editMessageText`, webhook commands, bot commands и `answerCallbackQuery` вынесены из `Application` в `src/BotApiController.php`. `Application` остается composition root и router, который делегирует Bot API requests без изменения HTTP-контрактов.

Второй шаг выполнен: UI-маршруты `/chat`, `/chat/fragment`, `/chat/send`, `/chat/callback` и `/chat/clear` вынесены из `Application` в `src/ChatController.php`. Шаблон `templates/chat/index.php`, redirects, HTMX-фрагмент, keyboards и group chat behavior сохранены.

Framework стоит пересмотреть, если одновременно выполняются несколько условий:

- число маршрутов и handlers продолжает расти;
- `Application` не удается декомпозировать локальными классами без имитации framework;
- появляются повторяющиеся middleware-сценарии: auth, CSRF, rate limiting, content negotiation;
- Composer dependency становится приемлемой частью Docker-first workflow.

## Последствия

- Новых зависимостей нет.
- Docker Compose запуск не меняется.
- Тесты продолжают запускаться текущими командами.
- Будущие задачи по модернизации должны сначала уменьшать ответственность `Application`, а не заменять router ради router.

# ADR: стратегия тестирования

## Статус

Принято: оставить текущий самописный Docker HTTP smoke runner `tests/bot_api_test.php` как основной тестовый контур на текущем этапе.

## Контекст

Проект запускается Docker-first на готовом образе `php:8.3-cli-alpine` без Composer dependencies. Текущий тестовый файл поднимает встроенный PHP HTTP server, отдельный webhook receiver и проверяет реальные HTTP routes, SQLite storage, webhook delivery, Long Polling, Bot API payloads, UI smoke HTML и базовые unit-проверки `UpdateGenerator`.

Это дает высокую ценность для локального эмулятора: тест проверяет не только отдельные функции, но и фактический workflow, который используют bot containers.

## Варианты

### Оставить один самописный runner

Плюсы:

- не нужны новые зависимости;
- полностью совпадает с Docker-first workflow;
- проверяет реальный HTTP server и filesystem runtime;
- удобно запускать одной командой в README.

Минусы:

- файл растет и становится менее удобным для навигации;
- нельзя легко запускать отдельные группы тестов;
- assertions и fixtures остаются самописными.

### Разделить самописный runner на модули

Плюсы:

- сохраняет отсутствие зависимостей;
- позволяет разделить helpers, fixtures, Bot API сценарии, UI smoke и repository/unit checks;
- проще добавлять новые сценарии без раздувания одного файла.

Минусы:

- нужен небольшой bootstrap;
- придется аккуратно сохранить порядок интеграционных сценариев или изолировать state между группами.

### Добавить PHPUnit

Плюсы:

- стандартный test runner и assertions;
- удобная фильтрация тестов;
- проще разделять unit/integration suites.

Минусы:

- потребуется Composer и dev dependency;
- Docker-first запуск станет сложнее;
- текущие end-to-end HTTP checks все равно придется сохранить или переписать как integration suite;
- польза ограничена, пока проект маленький и без Composer.

## Решение

Сейчас оставить `tests/bot_api_test.php` как основной smoke runner и не добавлять PHPUnit.

Ближайшая стратегия:

- продолжать добавлять focused assertions в `tests/bot_api_test.php` для новых Bot API methods, payload structures, Long Polling, webhook delivery, storage и UI smoke;
- держать команду запуска в README;
- запускать PHP lint отдельно;
- при существенном росте файла выделить helpers и сценарии в `tests/support/` и `tests/scenarios/`, но сохранить один entrypoint;
- PHPUnit подключать только после появления Composer в проекте или явной потребности в независимых unit suites.

## Критерии пересмотра

Решение нужно пересмотреть, если:

- `tests/bot_api_test.php` становится трудно поддерживать из-за размера и порядка сценариев;
- требуется запускать отдельные test groups в CI;
- появляются сложные unit-тестируемые сервисы вне HTTP workflow;
- Composer уже добавлен по другой причине.

## Команды

Основной smoke runner:

```bash
docker compose run --rm --no-deps telegram-emulator sh -lc "php tests/bot_api_test.php"
```

PHP lint:

```bash
docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"
```

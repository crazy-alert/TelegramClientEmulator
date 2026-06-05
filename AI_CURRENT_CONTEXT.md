# Текущий контекст проекта

## Последнее обновление

2026-06-06: актуализированы README/Roadmap, добавлена безопасная проверка обновлений, обработан и удален устаревший `AI_PROPOSALS.md`.

Что сделано:

- `README.md` переписан как пользовательское описание: что такое эмулятор, зачем он нужен, как установить Docker Desktop и Git на Windows, как запустить проект, настроить порты, hosts, reverse proxy, Long Polling и webhook.
- `ROADMAP.md` приведен к фактическому состоянию: MVP локального developer workflow отмечен готовым, устаревшие пункты про inspector/media/renderer убраны, остаток перенесен в будущие задачи и ограничения.
- В `ROADMAP.md` добавлена пометка, что roadmap и исторические предложения из `AI_PROPOSALS.md` были самостоятельно подготовлены AI-агентом Codex (ChatGPT 5.5).
- Остаточные предложения из `AI_PROPOSALS.md` перенесены в `ROADMAP.md`: дальнейшее уменьшение ответственности `Application`, возможная декомпозиция `ImportExportController`, release-процесс для `version.json`, роли/служебные события групп, архивный media export/import и будущие Bot API методы.
- `AI_PROPOSALS.md` удален как устаревший рабочий файл.
- `AI_PROJECT_MAP.md` обновлен: удалена ссылка на `AI_PROPOSALS.md`, добавлены `version.json` и `src/UpdateChecker.php`.
- Добавлен `version.json` с локальным commit hash и URL проверки обновлений.
- Добавлен `src/UpdateChecker.php`: сравнивает локальный `version.json` с удаленным JSON по HTTPS, без запуска Git или shell из PHP.
- На `templates/dashboard.php` добавлен блок `Обновления TelegramEmulator` с кнопкой проверки и ручными командами обновления через `git pull`.
- В `Application` добавлен POST-маршрут `/telegram-emulator-updates/check`.
- Smoke-тест панели обновлен: проверяет кнопку обновления, безопасное пояснение и ручную инструкцию `git pull` через локальный fake update endpoint.
- Поиск файлов-призраков: явно удален только `AI_PROPOSALS.md`; `AI_WORK_PLAN_COMPLITED.md` оставлен из-за правил `AGENTS.md`, redirect-файлы `CLAUDE.md`, `GEMINI.md`, `.cursorrules`, `.windsurfrules`, `.github/copilot-instructions.md` оставлены как потенциально полезные для других AI-инструментов.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php -l src/UpdateChecker.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l src/Application.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l public/index.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php -l templates/dashboard.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно после обработки `AI_PROPOSALS.md`.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Текущая пользовательская задача выполнена. Если потребуется дополнительная чистка файлов, сначала согласовать удаление redirect-файлов для сторонних AI-инструментов.

## Важные решения

- Проект Docker-first; проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- Проверка обновлений не запускает `git`, `shell`, `proc_open`, `exec` и не меняет файлы проекта из PHP.
- `version.json` является локальным источником установленного hash; удаленный `version.json` является источником последнего опубликованного hash.
- Runtime-данные остаются в `data/` и `var/logs/`, обычный `git pull` не должен их перезаписывать.

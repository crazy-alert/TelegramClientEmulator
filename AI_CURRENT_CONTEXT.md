# Текущий контекст проекта

## Последнее обновление

2026-06-05: выполнена `.aitasks/task03-media-preview-download-links.md`.

Что сделано:

- `MessageRenderer` добавляет `download_url` для найденных локальных `local-media:<sha256>` файлов.
- Для локальных image media renderer добавляет `preview_url`.
- `/chat` показывает компактный `<img class="media-preview">` и ссылку `Скачать` для локальных media; внешние URL остаются только текстовым source.
- `ChatController` передает `MediaStorage` в шаблон чата.
- Обновлены `README.md`, `docs/technical-spec.md`, `docs/limitations.md` и `AI_PROJECT_MAP.md`.

Проверки:

- `docker compose run --rm --no-deps telegram-emulator php tests/message_renderer_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_payload_factory_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php` — успешно.
- `docker compose run --rm --no-deps telegram-emulator sh -lc "find src public templates tests -name '*.php' -print0 | xargs -0 -n1 php -l"` — успешно.
- `git diff --check` — успешно; есть только предупреждения Git о будущей CRLF-нормализации на Windows.

## Ближайшая очередь

Оставшиеся задачи в `.aitasks/`:

- `task04-application-decomposition.md`
- `task05-split-http-scenarios.md`
- `task06-webhook-retry-backoff.md`
- `task07-group-chat-model.md`
- `task08-fixture-packs-import-export.md`
- `task09-long-polling-timeout-model.md`
- `task10-command-scopes-language.md`
- `task11-http-log-inspector.md`
- `task12-bot-api-surface-catalog.md`

Следующий шаг: взять `task04-application-decomposition.md`, обновить `AI_WORK_PLAN.md` и начать декомпозицию `Application` с одного безопасного среза.

## Важные решения

- Проект Docker-first; на хосте может не быть `php`, проверки запускать через `docker compose run`.
- Не добавлять неканоничные Telegram Bot API aliases/shortcuts без явного запроса.
- `BotApiParams` отвечает за parsing/нормализацию параметров, но не за HTTP response policy.
- `BotApiPayloadFactory` отвечает за чистую сборку response payload и не должен обращаться к БД, HTTP response helpers или runtime storage.
- `LongPollingService` отвечает за queue/offset/allowed_updates logic и не должен формировать HTTP response или проверять webhook conflict.

# Активный план работы

## Текущая задача

Проверить, нужен ли nginx/sub_filter для warning `multipart/form-data`, перейти на готовые Docker-образы без сборки через Dockerfile и запустить `docker-compose.yml`.

## Чеклист

- [completed] Воспроизвести malformed multipart напрямую на `php -S` без nginx.
- [completed] Убрать nginx/sub_filter и Dockerfile из обычного runtime, если проверка подтверждает чистый JSON.
- [completed] Обновить Docker Compose и документацию по запуску.
- [completed] Запустить `docker compose up` и проверить `/health`.
- [completed] Обновить рабочий контекст, сделать коммит и push.

## Заметки

- Рабочее дерево на старте чистое: `master...origin/master`.
- `php:8.3-cli-alpine` уже содержит `pdo_sqlite` и `sqlite3`, отдельная сборка для SQLite не нужна.
- Прямой `php -S` с `Content-Type: multipart/form-data` без boundary вернул чистый JSON без warning в body.

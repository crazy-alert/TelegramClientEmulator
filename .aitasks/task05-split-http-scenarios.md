# Задача 05: Дробление HTTP scenarios

## Цель

Разделить крупный `tests/scenarios/http_scenarios.php` на тематические scenario-файлы.

## Ожидаемый результат

- Появляются отдельные файлы вроде `chat_ui_scenarios.php`, `media_scenarios.php`, `webhook_scenarios.php`, `long_polling_scenarios.php`, `import_export_scenarios.php`.
- `tests/bot_api_test.php` остается основным entrypoint.
- Существующие HTTP tests проходят.
- README/ADR/project map обновлены, если меняется test layout.

## Ограничения

- Не менять смысл сценариев одновременно с механическим переносом.

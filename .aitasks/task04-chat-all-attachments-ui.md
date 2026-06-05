# Задача 04: расширить UI отправки вложений до всей поддерживаемой Bot API surface

Цель: форма `/chat` должна позволять отправлять все основные типы, которые уже поддерживает backend.

Минимальный результат:

- добавить компактные формы для `video`, `animation`, `audio`, `voice`, `video_note`, `sticker`, `poll`, `venue`, `dice`;
- сохранить раскрывающийся блок `Вложения` и плотную верстку для маленьких экранов;
- переиспользовать существующий `/chat/send` и `message_type`;
- добавить DOM/HTTP checks на наличие форм и создание updates;
- обновить `README.md`/`docs/limitations.md`, если меняется описание UI.

Проверки:

- syntax check измененных templates/tests;
- `docker compose run --rm --no-deps telegram-emulator php tests/bot_api_test.php`;
- `git diff --check`.

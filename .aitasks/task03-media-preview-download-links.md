# Задача 03: Preview и download links для media в UI

## Цель

Сделать локальные media-сообщения полезнее в `/chat`: показывать download link для `local-media:<sha256>` и компактный preview для локальных изображений.

## Ожидаемый результат

- В media block появляется ссылка на локальный `/file/bot<TOKEN>/<file_path>`, если файл доступен через `MediaStorage`.
- Для локальных image media показывается компактный `<img>` preview.
- Внешние URL не preview-ятся.
- Верстка остается лаконичной на маленьких экранах.
- Добавлены DOM/HTTP regression checks.

## Ограничения

- Не раскрывать реальные token/секреты в UI.
- Не ломать текущий text/source rendering для media.

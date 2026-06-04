# Примеры интеграции bot frameworks

Все примеры предполагают, что бот и эмулятор находятся в одной Docker Compose сети, а сервис эмулятора называется `telegram-emulator`.

Используйте fake token из UI эмулятора, например:

```env
TELEGRAM_BOT_TOKEN=123456:local-dev-token
TELEGRAM_API_BASE_URL=http://telegram-emulator:8080
```

Не используйте `localhost` из контейнера бота для обращения к эмулятору: внутри контейнера `localhost` указывает на сам контейнер. Для browser-доступа с хоста можно открыть `http://localhost:8080`, но контейнеры должны ходить друг к другу через service DNS.

## PHP: обычный HTTP-клиент

Если бот не использует SDK или SDK трудно перенастроить, можно вызывать локальный Bot API напрямую.

```php
<?php

$token = getenv('TELEGRAM_BOT_TOKEN') ?: '123456:local-dev-token';
$apiBaseUrl = getenv('TELEGRAM_API_BASE_URL') ?: 'http://telegram-emulator:8080';

$payload = http_build_query([
    'chat_id' => 1001,
    'text' => 'Hello from PHP',
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
    ],
]);

$response = file_get_contents($apiBaseUrl . '/bot' . $token . '/sendMessage', false, $context);
var_dump($response);
```

Если библиотека сама дописывает `/bot<TOKEN>/<METHOD>`, задавайте ей base URL `http://telegram-emulator:8080`. Если библиотека ожидает префикс до token и сама дописывает token без `/bot`, используйте `http://telegram-emulator:8080/bot`.

## python-telegram-bot

`ApplicationBuilder.base_url()` задает базовый URL для Bot API. Для формы Telegram Bot API нужен префикс `/bot`, поэтому используйте `http://telegram-emulator:8080/bot`.

```python
import os
from telegram import Update
from telegram.ext import ApplicationBuilder, CommandHandler, ContextTypes

TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "123456:local-dev-token")

async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await update.message.reply_text("Hello from python-telegram-bot")

app = (
    ApplicationBuilder()
    .token(TOKEN)
    .base_url("http://telegram-emulator:8080/bot")
    .build()
)

app.add_handler(CommandHandler("start", start))
app.run_polling()
```

Файловые URL для `getFile` в эмуляторе пока не реализованы, поэтому `base_file_url` обычно не нужен.

## aiogram

В aiogram используйте custom API server через `AiohttpSession` и `TelegramAPIServer.from_base(...)`.

```python
import asyncio
import os

from aiogram import Bot, Dispatcher, Router
from aiogram.client.session.aiohttp import AiohttpSession
from aiogram.client.telegram import TelegramAPIServer
from aiogram.filters import Command
from aiogram.types import Message

TOKEN = os.getenv("TELEGRAM_BOT_TOKEN", "123456:local-dev-token")

router = Router()

@router.message(Command("start"))
async def start(message: Message) -> None:
    await message.answer("Hello from aiogram")

async def main() -> None:
    session = AiohttpSession(
        api=TelegramAPIServer.from_base("http://telegram-emulator:8080")
    )
    bot = Bot(TOKEN, session=session)
    dispatcher = Dispatcher()
    dispatcher.include_router(router)
    await dispatcher.start_polling(bot)

asyncio.run(main())
```

## grammY

В grammY локальный Bot API root задается через `client.apiRoot`.

```ts
import { Bot } from "grammy";

const token = process.env.TELEGRAM_BOT_TOKEN ?? "123456:local-dev-token";

const bot = new Bot(token, {
  client: {
    apiRoot: "http://telegram-emulator:8080",
  },
});

bot.command("start", (ctx) => ctx.reply("Hello from grammY"));
bot.start();
```

## Telegraf

В Telegraf можно передать custom API root в опции `telegram.apiRoot`.

```ts
import { Telegraf } from "telegraf";

const token = process.env.TELEGRAM_BOT_TOKEN ?? "123456:local-dev-token";

const bot = new Telegraf(token, {
  telegram: {
    apiRoot: "http://telegram-emulator:8080",
  },
});

bot.start((ctx) => ctx.reply("Hello from Telegraf"));
bot.launch();
```

## Webhook режим

Для webhook-бота в UI эмулятора задайте URL на service DNS бота:

```text
http://bot:3000/telegram/webhook
```

Бот при этом должен слушать `0.0.0.0` внутри контейнера, а не только `127.0.0.1`.

## Источники

- python-telegram-bot: `ApplicationBuilder.base_url()` — https://docs.python-telegram-bot.org/en/latest/telegram.ext.applicationbuilder.html
- aiogram: custom API server через `TelegramAPIServer.from_base()` — https://docs.aiogram.dev/uk-ua/latest/api/session/custom_server.html
- grammY: `client.apiRoot` — https://grammy.dev/ref/core/apiclientoptions
- Telegraf: constructor options и `telegram` options — https://telegraf.js.org/interfaces/telegraf.options.html

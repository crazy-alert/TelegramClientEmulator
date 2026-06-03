<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Telegram Bot Emulator') ?> · Telegram Bot Emulator</title>
    <script src="https://unpkg.com/htmx.org@1.9.12" defer></script>
    <style>
        :root {
            color-scheme: light;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f7f9;
            color: #17212b;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
        }

        a {
            color: #2481cc;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        header {
            background: #ffffff;
            border-bottom: 1px solid #d8e1e8;
        }

        nav,
        main {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 20px;
        }

        nav {
            display: flex;
            align-items: center;
            gap: 18px;
            min-height: 56px;
        }

        nav strong {
            margin-right: 12px;
        }

        main {
            padding-top: 28px;
            padding-bottom: 48px;
        }

        h1 {
            margin: 0 0 20px;
            font-size: 28px;
            font-weight: 700;
        }

        h2 {
            margin: 0 0 14px;
            font-size: 20px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 18px;
        }

        .panel,
        form.editor {
            background: #ffffff;
            border: 1px solid #d8e1e8;
            border-radius: 8px;
            padding: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
            border: 1px solid #d8e1e8;
            border-radius: 8px;
            overflow: hidden;
        }

        th,
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #e6edf2;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eef4f8;
            font-size: 13px;
            color: #536471;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        label {
            display: grid;
            gap: 6px;
            margin-bottom: 14px;
            font-weight: 650;
        }

        input,
        select {
            width: 100%;
            min-height: 38px;
            padding: 8px 10px;
            border: 1px solid #c8d3dc;
            border-radius: 6px;
            font: inherit;
        }

        .checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox input {
            width: auto;
            min-height: 0;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        button,
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 8px 12px;
            border: 1px solid #2481cc;
            border-radius: 6px;
            background: #2481cc;
            color: #ffffff;
            font: inherit;
            cursor: pointer;
        }

        .button.secondary,
        button.secondary {
            background: #ffffff;
            color: #2481cc;
        }

        button.danger {
            border-color: #cc3d3d;
            background: #cc3d3d;
        }

        .muted {
            color: #647482;
        }

        .field-error {
            color: #b42318;
            font-size: 13px;
            font-weight: 500;
        }

        code {
            font-family: "JetBrains Mono", Consolas, monospace;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 18px;
            background: #ffffff;
            border: 1px dashed #b7c6d1;
            border-radius: 8px;
            color: #536471;
        }

    </style>
</head>
<body>
<header>
    <nav>
        <strong>Telegram Bot Emulator</strong>
        <a href="/">Панель</a>
        <a href="/chat">Чат</a>
        <a href="/updates">Updates</a>
        <a href="/bots">Боты</a>
        <a href="/profiles">Пользователи</a>
        <a href="/delivery-attempts">Webhook attempts</a>
        <a href="/health">Health</a>
    </nav>
</header>
<main>
    <?php require $contentTemplate; ?>
</main>
</body>
</html>

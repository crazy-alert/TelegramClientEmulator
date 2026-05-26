CREATE TABLE bots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token TEXT NOT NULL UNIQUE,
    bot_id INTEGER,
    username TEXT NOT NULL,
    display_name TEXT NOT NULL,
    delivery_mode TEXT NOT NULL DEFAULT 'long_polling' CHECK (delivery_mode IN ('webhook', 'long_polling')),
    webhook_url TEXT,
    webhook_secret_token TEXT,
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE profiles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    active_bot_id INTEGER REFERENCES bots(id) ON DELETE SET NULL,
    user_id INTEGER NOT NULL,
    username TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT,
    chat_id INTEGER NOT NULL,
    chat_type TEXT NOT NULL DEFAULT 'private',
    language_code TEXT NOT NULL DEFAULT 'ru',
    enabled INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bot_id INTEGER NOT NULL REFERENCES bots(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    chat_id INTEGER NOT NULL,
    telegram_message_id INTEGER NOT NULL,
    direction TEXT NOT NULL CHECK (direction IN ('user', 'bot')),
    text TEXT NOT NULL,
    raw_payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE updates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bot_id INTEGER NOT NULL REFERENCES bots(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    update_id INTEGER NOT NULL,
    payload TEXT NOT NULL,
    delivery_mode TEXT NOT NULL CHECK (delivery_mode IN ('webhook', 'long_polling')),
    queue_state TEXT NOT NULL DEFAULT 'pending' CHECK (queue_state IN ('pending', 'delivered', 'confirmed', 'failed')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at TEXT,
    confirmed_at TEXT,
    UNIQUE (bot_id, update_id)
);

CREATE TABLE delivery_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    update_row_id INTEGER NOT NULL REFERENCES updates(id) ON DELETE CASCADE,
    bot_id INTEGER NOT NULL REFERENCES bots(id) ON DELETE CASCADE,
    webhook_url TEXT NOT NULL,
    request_headers TEXT,
    request_body TEXT NOT NULL,
    response_status INTEGER,
    response_headers TEXT,
    response_body TEXT,
    duration_ms INTEGER,
    error TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_messages_bot_profile ON messages(bot_id, profile_id, created_at);
CREATE INDEX idx_updates_bot_state ON updates(bot_id, queue_state, update_id);
CREATE INDEX idx_delivery_attempts_update ON delivery_attempts(update_row_id, created_at);


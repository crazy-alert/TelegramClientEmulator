CREATE TABLE messages_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bot_id INTEGER NOT NULL REFERENCES bots(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    chat_id INTEGER NOT NULL,
    telegram_message_id INTEGER NOT NULL,
    direction TEXT NOT NULL CHECK (direction IN ('user', 'bot', 'service')),
    text TEXT NOT NULL,
    raw_payload TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO messages_new (
    id,
    bot_id,
    profile_id,
    chat_id,
    telegram_message_id,
    direction,
    text,
    raw_payload,
    created_at
)
SELECT
    id,
    bot_id,
    profile_id,
    chat_id,
    telegram_message_id,
    direction,
    text,
    raw_payload,
    created_at
FROM messages;

DROP TABLE messages;
ALTER TABLE messages_new RENAME TO messages;

CREATE INDEX idx_messages_bot_profile ON messages(bot_id, profile_id, created_at);

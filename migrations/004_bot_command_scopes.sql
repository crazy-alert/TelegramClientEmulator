CREATE TABLE bot_commands_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bot_id INTEGER NOT NULL REFERENCES bots(id) ON DELETE CASCADE,
    scope_type TEXT NOT NULL DEFAULT 'default',
    scope_key TEXT NOT NULL DEFAULT 'default',
    scope_json TEXT NOT NULL DEFAULT '{"type":"default"}',
    language_code TEXT NOT NULL DEFAULT '',
    command TEXT NOT NULL,
    description TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (bot_id, scope_key, language_code, command)
);

INSERT INTO bot_commands_new (
    id,
    bot_id,
    scope_type,
    scope_key,
    scope_json,
    language_code,
    command,
    description,
    position,
    created_at,
    updated_at
)
SELECT
    id,
    bot_id,
    'default',
    'default',
    '{"type":"default"}',
    '',
    command,
    description,
    position,
    created_at,
    updated_at
FROM bot_commands;

DROP TABLE bot_commands;
ALTER TABLE bot_commands_new RENAME TO bot_commands;

CREATE INDEX idx_bot_commands_bot_position ON bot_commands(bot_id, position, id);
CREATE INDEX idx_bot_commands_bot_scope_language_position
    ON bot_commands(bot_id, scope_key, language_code, position, id);

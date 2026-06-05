CREATE TABLE chats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_id INTEGER NOT NULL UNIQUE,
    type TEXT NOT NULL CHECK (type IN ('private', 'group', 'supergroup', 'channel')),
    title TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chat_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    chat_row_id INTEGER NOT NULL REFERENCES chats(id) ON DELETE CASCADE,
    profile_id INTEGER NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'member',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (chat_row_id, profile_id)
);

INSERT INTO chats (chat_id, type, title)
SELECT
    grouped.chat_id,
    grouped.chat_type,
    CASE
        WHEN grouped.chat_type IN ('group', 'supergroup', 'channel') THEN 'Chat ' || grouped.chat_id
        ELSE grouped.title
    END AS title
FROM (
    SELECT
        chat_id,
        MIN(chat_type) AS chat_type,
        MIN(TRIM(first_name || ' ' || COALESCE(last_name, ''))) AS title
    FROM profiles
    GROUP BY chat_id
) grouped;

INSERT INTO chat_members (chat_row_id, profile_id)
SELECT chats.id, profiles.id
FROM profiles
INNER JOIN chats ON chats.chat_id = profiles.chat_id;

CREATE INDEX idx_chats_chat_id ON chats(chat_id);
CREATE INDEX idx_chat_members_profile ON chat_members(profile_id);

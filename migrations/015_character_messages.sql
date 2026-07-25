-- THE DEAD LAST — migration 015: character_messages (Boss 2026-07-25).
-- Talk-bubble lines now belong to authored catalog characters (014), edited
-- from a "Messages" button on each character row. Keyed to characters.id
-- (was previously keyed to the game-synced npc_roster.name in npc_messages,
-- which is left in place as legacy). Any character type can have lines.

CREATE TABLE IF NOT EXISTS character_messages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    character_id INTEGER NOT NULL,
    body         TEXT NOT NULL,
    enabled      INTEGER NOT NULL DEFAULT 1,
    weight       INTEGER NOT NULL DEFAULT 1,          -- relative pick weight (future)
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_character_messages_char ON character_messages(character_id);

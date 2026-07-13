-- THE DEAD LAST — migration 008: NPC talk-bubble messages
-- Central authoring of the lines generic NPCs say in overhead talk bubbles,
-- managed from Keeper > Messages. Two tables:
--
--   npc_roster   — the spawnable NPCs the messages attach to. The roster is
--                  DYNAMIC (it changes as characters are added/removed in the
--                  game), so the site does not hardcode it: Keeper's "Update
--                  roster" button fetches the current list from the game's
--                  roster endpoint (env GAME_ROSTER_URL) and upserts here.
--                  Characters missing from the latest fetch are marked
--                  active = 0 (kept, not deleted) so their saved lines survive
--                  a character being temporarily removed.
--
--   npc_messages — the lines. One row per line; many lines per NPC. The game
--                  pulls the enabled lines and picks random ones to speak.
--
-- npc_name is the roster/character `name` (e.g. "Eddie") — the same join key
-- the game uses (CharacterRoster.json `name` / SkinEntry.skinName).

CREATE TABLE IF NOT EXISTS npc_roster (
    name       TEXT PRIMARY KEY,                       -- character name, e.g. "Eddie" (join key)
    gender     TEXT,                                   -- 'm' | 'f' (from the game roster, optional)
    role       TEXT,                                   -- 'human' | 'zombie' | 'npc' | 'base' (optional)
    height     REAL,                                   -- from the game roster (optional, informational)
    active     INTEGER NOT NULL DEFAULT 1,             -- 1 = in the latest fetched roster; 0 = gone (lines kept)
    seen_at    DATETIME DEFAULT CURRENT_TIMESTAMP      -- last time this NPC appeared in a roster fetch
);

CREATE TABLE IF NOT EXISTS npc_messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    npc_name   TEXT NOT NULL,                          -- FK -> npc_roster.name (the speaker)
    body       TEXT NOT NULL,                          -- the line spoken in the talk bubble
    enabled    INTEGER NOT NULL DEFAULT 1,             -- 1 = eligible to be spoken; 0 = kept but muted
    weight     INTEGER NOT NULL DEFAULT 1,             -- optional relative pick weight (>=1)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (npc_name) REFERENCES npc_roster(name) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_npc_messages_npc ON npc_messages(npc_name);

-- THE DEAD LAST — migration 006: characters
-- Every account can play many characters over time (permadeath: each True
-- Death ends one and the player rolls a new survivor). One row per character
-- the game starts, created through the stats ingest API (api/stats/), which
-- also increments player_stats.lives — a new character IS a new life.
-- `ref` is an OPAQUE identifier string minted by the game (its own
-- convention, likely username_id_name); the server stores it verbatim and
-- never parses or validates its structure — lookups are exact-string
-- equality scoped to the user. Per-character stat columns may come later;
-- user-level aggregates stay in player_stats.
-- See docs/player-stats.md and docs/game-stats-api.md.

CREATE TABLE IF NOT EXISTS characters (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,                       -- owning account
    ref        TEXT NOT NULL,                          -- opaque game-side identifier, stored verbatim
    name       TEXT,                                   -- optional character name, if sent separately
    skin       TEXT NOT NULL,                          -- skin identifier the character was created with
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,     -- when the character was first spawned
    ended_at   DATETIME,                               -- set when the character's run ends
    outcome    TEXT,                                   -- free text for now, e.g. died | turned | true_death
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_characters_user_id ON characters(user_id);
CREATE INDEX IF NOT EXISTS idx_characters_ref     ON characters(ref);

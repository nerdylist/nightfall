-- THE DEAD LAST — migration 014: characters catalog (Boss 2026-07-25).
-- The authored roster of the game's actual characters (meshes / skins): the
-- Humans, NPCs, and Zombies that populate the world. Authored in Keeper
-- (Characters tab) and destined to be piped into the game's RUNTIME STARTUP as
-- the canonical list of characters running in the game (a GET /api/characters
-- feed is the planned follow-up — keep this shape API-friendly).
--
-- Distinct from `survivors` (a user's permadeath life, renamed from the old
-- `characters` in 013) and from `npc_roster` (the game-reported spawnable-NPC
-- list that drives talk-bubble messages). This table is hand-authored source
-- of truth for who exists in the game.

CREATE TABLE IF NOT EXISTS characters (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,                        -- display name
    age         INTEGER,                              -- nullable (NPCs/Zombies may have none)
    gender      TEXT,                                 -- 'm' | 'f' | 'unknown'
    type        TEXT NOT NULL DEFAULT 'Human',        -- 'Human' | 'NPC' | 'Zombie' (extensible)
    description TEXT,                                  -- optional bio/notes
    avatar_path TEXT,                                  -- headshot image path (assets/characters/…)
    pose_path   TEXT,                                  -- full-body / pose image path
    sort_order  INTEGER NOT NULL DEFAULT 0,           -- manual ordering in the admin
    active      INTEGER NOT NULL DEFAULT 1,           -- included in the runtime feed when 1
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_characters_type   ON characters(type);
CREATE INDEX IF NOT EXISTS idx_characters_active ON characters(active);

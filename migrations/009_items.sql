-- THE DEAD LAST — migration 009: in-game item catalog
-- A mirror of the GAME's item registry (C# ItemDatabase + ItemMeta + weapon
-- damage + asset paths). The game is the source of truth; an editor export tool
-- POSTs the full item list up to /api/items (Bearer GAME_API_KEY), which
-- REPLACES the table (delete-all + insert) so it stays in sync as items are
-- added/removed. GET /api/items serves it as JSON (public read) — used while
-- authoring item thumbnails / to see what's in the game.
--
-- DYNAMIC: the game decides the columns' values; the site just stores/serves.
-- Any item field the game sends is kept; the schema covers the known set and a
-- freeform `extra` JSON blob for anything new so the endpoint never has to
-- change when the game adds a field.

CREATE TABLE IF NOT EXISTS items (
    item_id      TEXT PRIMARY KEY,                 -- game ItemDef.id (e.g. "medkit")
    display_name TEXT NOT NULL,                    -- ItemDef.displayName
    category     TEXT,                             -- ItemCategory (Food/Weapon/Medicine/...)
    rarity       TEXT,                             -- ItemMeta.rarity
    stackable    INTEGER NOT NULL DEFAULT 0,       -- 0/1
    max_stack    INTEGER NOT NULL DEFAULT 1,
    power        INTEGER,                          -- weapon damage (null for non-weapons)
    weight_kg    REAL,                             -- ItemMeta.weightKg
    value        INTEGER,                          -- ItemMeta.value (dollars)
    durability   INTEGER,                          -- ItemMeta.durability (0..100)
    description  TEXT,                             -- ItemMeta.description
    used_to      TEXT,                             -- ItemMeta.usedTo
    thumbnail    TEXT,                             -- relative thumbnail path (icons/items/<id>.png ...)
    model        TEXT,                             -- 3D model reference (Items.fbx root, or null = ui-only)
    extra        TEXT,                             -- freeform JSON for future fields
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

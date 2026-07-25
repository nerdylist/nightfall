-- THE DEAD LAST — migration 013: rename "characters" -> "survivors"
-- (Boss 2026-07-25). The game-spawned, permadeath player-run rows were named
-- `characters`, but that name is being freed for a separate authored character
-- roster feature. These rows are SURVIVORS — one per life a player runs until
-- True Death. Rename the table, its child tables, and their FK columns to
-- match. SQLite's ALTER TABLE ... RENAME TO automatically rewrites FK target
-- references in dependent tables' schemas, and RENAME COLUMN updates the PK/FK
-- column names in place; existing indexes carry over with the table.
--
-- Applied via migrate.php (per-file transaction; skipped once recorded).
--
-- COORDINATED CHANGE: the API request/response field names also change
-- (character_id -> survivor_id, character_ref -> survivor_ref, the "character"
-- object -> "survivor"). The game client (StatsReporter.cs) MUST be updated to
-- match — see the game repo handoff note. Do NOT deploy this in isolation.

-- Ensure ALTER TABLE ... RENAME rewrites FK *target* references in dependent
-- tables (the modern behavior). The PDO driver defaults this OFF already, but
-- set it explicitly so the rename is deterministic under any driver/CLI. This
-- pragma is transaction-safe (unlike PRAGMA foreign_keys).
PRAGMA legacy_alter_table=OFF;

-- 1) Parent table: characters -> survivors.
--    Auto-updates the FK targets in character_stats / character_playtime.
ALTER TABLE characters RENAME TO survivors;

-- 2) Child stats table + its FK/PK column.
ALTER TABLE character_stats RENAME TO survivor_stats;
ALTER TABLE survivor_stats RENAME COLUMN character_id TO survivor_id;

-- 3) Child daily-playtime table + its FK column (part of the composite PK).
ALTER TABLE character_playtime RENAME TO survivor_playtime;
ALTER TABLE survivor_playtime RENAME COLUMN character_id TO survivor_id;

-- 4) Rename indexes to match (drop + recreate; index bodies now point at the
--    renamed tables/columns automatically, but the NAMES still say "character").
DROP INDEX IF EXISTS idx_characters_user_id;
DROP INDEX IF EXISTS idx_characters_ref;
DROP INDEX IF EXISTS idx_character_playtime_date;

CREATE INDEX IF NOT EXISTS idx_survivors_user_id       ON survivors(user_id);
CREATE INDEX IF NOT EXISTS idx_survivors_ref           ON survivors(ref);
CREATE INDEX IF NOT EXISTS idx_survivor_playtime_date  ON survivor_playtime(date);

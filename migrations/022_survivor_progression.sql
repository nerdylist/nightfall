-- THE DEAD LAST — migration 022: survivor progression fields (Boss/Nancy 2026-07-29).
-- Attributes now drive HP / damage / loot luck and feed profiles + boards, so
-- the authoritative copy moves off local PlayerPrefs onto the survivor record.
-- See the game repo: docs/ROADMAP/survivor-progression-handoff.md.
--
-- Eight attributes (0-10), cumulative lifetime XP, and how many earned points
-- have been spent. points_earned is NOT stored — it derives from xp via the
-- game-side curve so that curve stays tunable without a migration.
--
-- All default 0 / NULL-safe: a survivor created before this migration reads as
-- a fresh 0/0/0 sheet until the game reports values.

ALTER TABLE survivors ADD COLUMN str INTEGER NOT NULL DEFAULT 0;  -- STRENGTH
ALTER TABLE survivors ADD COLUMN agi INTEGER NOT NULL DEFAULT 0;  -- AGILITY
ALTER TABLE survivors ADD COLUMN end INTEGER NOT NULL DEFAULT 0;  -- ENDURANCE
ALTER TABLE survivors ADD COLUMN int INTEGER NOT NULL DEFAULT 0;  -- INTELLIGENCE
ALTER TABLE survivors ADD COLUMN awa INTEGER NOT NULL DEFAULT 0;  -- AWARENESS
ALTER TABLE survivors ADD COLUMN luk INTEGER NOT NULL DEFAULT 0;  -- LUCK
ALTER TABLE survivors ADD COLUMN foc INTEGER NOT NULL DEFAULT 0;  -- FOCUS
ALTER TABLE survivors ADD COLUMN fai INTEGER NOT NULL DEFAULT 0;  -- FAITH

ALTER TABLE survivors ADD COLUMN xp INTEGER NOT NULL DEFAULT 0;           -- cumulative lifetime XP (monotonic)
ALTER TABLE survivors ADD COLUMN points_spent INTEGER NOT NULL DEFAULT 0; -- earned points spent

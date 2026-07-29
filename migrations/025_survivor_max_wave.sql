-- THE DEAD LAST — migration 025: survivor max_wave (Boss/Nancy 2026-07-29).
-- The survivor's LIFETIME deepest wave reached — an ABSOLUTE value the game
-- persists per survivor. See the game repo:
-- docs/ROADMAP/survivor-progression-handoff.md (Wave Runner board, §5/§7).
--
-- The game sends it absolute in the /api/stats flush (survivor: {id, max_wave});
-- the server MAX-MERGES it (max(stored, sent)) so out-of-order / retried
-- deliveries can only move it up, never down. Feeds the Wave Runner leaderboard
-- (its wave-5 minimum entry gate is enforced elsewhere, on the board query).
--
-- Defaults 0: a survivor created before this migration reads as wave 0 until the
-- game reports a value.
--
-- Applied via migrate.php. A bare ADD COLUMN is NOT idempotent, but the
-- migrate.php runner records applied files and skips them (same as 022), so
-- re-running is safe.

ALTER TABLE survivors ADD COLUMN max_wave INTEGER NOT NULL DEFAULT 0;  -- lifetime deepest wave (absolute, max-merged)

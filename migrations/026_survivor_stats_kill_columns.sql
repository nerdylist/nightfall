-- THE DEAD LAST — migration 026: survivor_stats kill columns (Boss/Nancy 2026-07-29).
-- survivor_stats was created in 012 (as character_stats, renamed to survivor_stats
-- in 013) with the season-board columns, but WITHOUT humans_killed / zombies_killed.
-- Those two counters were added to the per-survivor mirror later: the stats ingest
-- (api/stats/index.php, STATS_COLUMNS) routes them to survivor_stats, but the
-- columns never existed there. So any flush carrying a kill counter with a survivor
-- context ran INSERT INTO survivor_stats (survivor_id, zombies_killed) ... which
-- threw "no such column: zombies_killed", was swallowed by the transaction's catch
-- as a generic "Write failed." 500, and rolled back the WHOLE post. Since the game
-- always sends kills with a survivor context, every real flush 500'd.
--
-- These are counters (incremented by the sent delta, same semantics as the 012
-- counters). Default 0: a survivor row created before this migration reads as 0
-- until the game reports kills.
--
-- Applied via migrate.php. A bare ADD COLUMN is NOT idempotent, but the migrate.php
-- runner records applied files and skips them (same as 025), so re-running is safe.

ALTER TABLE survivor_stats ADD COLUMN humans_killed INTEGER NOT NULL DEFAULT 0;  -- counter
ALTER TABLE survivor_stats ADD COLUMN zombies_killed INTEGER NOT NULL DEFAULT 0; -- counter

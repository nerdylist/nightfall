-- THE DEAD LAST — migration 010: per-character daily playtime buckets
-- The season leaderboard metric (Boss, 2026-07-22) is PER-CHARACTER,
-- PER-REAL-DAY ACTIVE SURVIVAL TIME: "each 24 hours, how much time is the
-- character logging in-game". The game tracks a per-character daily log of
-- (real date, active seconds) and reports it on stat posts as
-- "daily_playtime": [{ "date": "YYYY-MM-DD", "seconds": <int> }, ...].
--
-- One row per (character, date). The game sends ABSOLUTE per-day totals, so
-- ingest UPSERTS with seconds = max(stored, sent) — resends are idempotent and
-- can only move a bucket up (monotonic), never down. Paused time (Bank / Home /
-- Exit) never counts; the game owns that accounting.
--
-- Leaderboard queries (api/leaderboard/):
--   - Season TOP  = SUM(seconds) over buckets whose date is inside the season
--                   window (settings season_start..season_end), per character.
--   - TODAY       = a single day's bucket per character.
-- Both are covered by the composite PK (character_id, date) — the date range
-- scan and the SUM group both walk the PK index, no separate sort needed.
-- See docs/game-stats-api.md (daily_playtime ingest) and the game's
-- docs/GAME_NOTES/season-clock-and-leaderboards.md (the metric ruling).

CREATE TABLE IF NOT EXISTS character_playtime (
    character_id INTEGER NOT NULL,                    -- FK -> characters.id
    date         TEXT NOT NULL,                       -- real calendar day, YYYY-MM-DD
    seconds      INTEGER NOT NULL DEFAULT 0,          -- absolute active seconds that day (max-merged on ingest)
    PRIMARY KEY (character_id, date),
    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);

-- "today" board and any single-day scan across all characters: walk by date.
CREATE INDEX IF NOT EXISTS idx_character_playtime_date ON character_playtime(date);

-- THE DEAD LAST — migration 005: player_stats bank + lives
-- Two more game stats for /u/{username} profiles and the leaderboard,
-- reported by the game through the stats ingest API (api/stats/):
--   bank  — the player's current currency balance. The GAME owns the balance,
--           so ingest SETs it (last write wins), unlike the counters.
--   lives — how many characters the player has started (initial spawn and
--           every restart after a True Death). Counter: ingest increments.
-- See docs/player-stats.md for the full stat -> game-event mapping.

ALTER TABLE player_stats ADD COLUMN bank  INTEGER NOT NULL DEFAULT 0; -- set: current currency balance (game-authoritative)
ALTER TABLE player_stats ADD COLUMN lives INTEGER NOT NULL DEFAULT 0; -- counter: character starts/restarts

-- Leaderboard sort index (richest players board).
CREATE INDEX IF NOT EXISTS idx_player_stats_bank ON player_stats(bank);

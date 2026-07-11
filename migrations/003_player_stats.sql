-- THE DEAD LAST — migration 003: player game stats
-- Lifetime aggregate stats per site account, pushed up from the game server
-- (ingest API to follow). One row per user, keyed to users.id; the game
-- resolves the row via the account's username. Counters only ever increment,
-- "biggest/longest" columns only ever take max(old, new).
-- Read by the /u/{username} profile page and the leaderboard (both upcoming).
-- See docs/player-stats.md for the full stat → game-event mapping.

CREATE TABLE IF NOT EXISTS player_stats (
    user_id              INTEGER PRIMARY KEY,           -- 1:1 with users.id
    humans_killed        INTEGER NOT NULL DEFAULT 0,    -- counter: humans this player killed (as human or zombie)
    zombies_killed       INTEGER NOT NULL DEFAULT 0,    -- counter: zombies this player killed (as human or zombie)
    times_turned         INTEGER NOT NULL DEFAULT 0,    -- counter: infections that completed the human -> zombie turn
    deaths               INTEGER NOT NULL DEFAULT 0,    -- counter: every character death (includes true deaths)
    true_deaths          INTEGER NOT NULL DEFAULT 0,    -- counter: permadeaths (character deleted, no comeback)
    redemptions          INTEGER NOT NULL DEFAULT 0,    -- counter: zombie -> human redemptions completed
    biggest_horde_size   INTEGER NOT NULL DEFAULT 0,    -- max: largest horde led/joined as a zombie (members incl. self)
    longest_life_seconds INTEGER NOT NULL DEFAULT 0,    -- max: longest single character lifetime, in seconds
    playtime_seconds     INTEGER NOT NULL DEFAULT 0,    -- counter: total in-game time across all characters, in seconds
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Leaderboard sort indexes (ORDER BY <stat> DESC LIMIT n).
CREATE INDEX IF NOT EXISTS idx_player_stats_humans_killed  ON player_stats(humans_killed);
CREATE INDEX IF NOT EXISTS idx_player_stats_zombies_killed ON player_stats(zombies_killed);
CREATE INDEX IF NOT EXISTS idx_player_stats_biggest_horde  ON player_stats(biggest_horde_size);
CREATE INDEX IF NOT EXISTS idx_player_stats_longest_life   ON player_stats(longest_life_seconds);
CREATE INDEX IF NOT EXISTS idx_player_stats_playtime       ON player_stats(playtime_seconds);

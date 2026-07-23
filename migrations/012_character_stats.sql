-- 012: Per-SURVIVOR board stats (Boss 2026-07-23: "toggle on the Boards
-- heading SURVIVOR | USER"). Same columns + merge semantics as the board
-- columns on player_stats (011); one row per character. The stats ingest
-- writes BOTH: player_stats (user aggregate) and this table (survivor view)
-- whenever the post carries a character_id.
-- NOTE: fills from deploy time forward — earlier flushes only wrote the
-- per-user aggregate, so survivor boards start fresh.

CREATE TABLE IF NOT EXISTS character_stats (
    character_id            INTEGER PRIMARY KEY REFERENCES characters(id),

    kills_hvz               INTEGER NOT NULL DEFAULT 0, -- counter
    kills_hvh               INTEGER NOT NULL DEFAULT 0, -- counter
    kills_zvz               INTEGER NOT NULL DEFAULT 0, -- counter
    kills_zvh               INTEGER NOT NULL DEFAULT 0, -- counter
    bat_kills               INTEGER NOT NULL DEFAULT 0, -- counter
    humans_infected         INTEGER NOT NULL DEFAULT 0, -- counter
    chests_looted           INTEGER NOT NULL DEFAULT 0, -- counter
    distance_m              INTEGER NOT NULL DEFAULT 0, -- counter
    banked_total            INTEGER NOT NULL DEFAULT 0, -- counter
    times_turned            INTEGER NOT NULL DEFAULT 0, -- counter
    redemptions             INTEGER NOT NULL DEFAULT 0, -- counter
    true_deaths             INTEGER NOT NULL DEFAULT 0, -- counter
    deaths                  INTEGER NOT NULL DEFAULT 0, -- counter
    playtime_seconds        INTEGER NOT NULL DEFAULT 0, -- counter
    bank                    INTEGER NOT NULL DEFAULT 0, -- set

    hunter_pure_kills       INTEGER NOT NULL DEFAULT 0, -- max
    allie_pure_kills        INTEGER NOT NULL DEFAULT 0, -- max
    biggest_horde_size      INTEGER NOT NULL DEFAULT 0, -- max
    longest_life_seconds    INTEGER NOT NULL DEFAULT 0, -- max
    died_rich               INTEGER NOT NULL DEFAULT 0, -- max
    insomniac_seconds       INTEGER NOT NULL DEFAULT 0, -- max
    long_walk_seconds       INTEGER NOT NULL DEFAULT 0, -- max
    kill_free_life_seconds  INTEGER NOT NULL DEFAULT 0, -- max

    lazarus_seconds         INTEGER NOT NULL DEFAULT 0, -- min (0 = unset)
    fastest_death_seconds   INTEGER NOT NULL DEFAULT 0  -- min (0 = unset)
);

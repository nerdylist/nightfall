-- 011: Leaderboard season boards (Boss brainstorm 2026-07-22).
-- Per-user columns on player_stats, fed by the game's CharacterStats ledger
-- via POST /api/stats. Semantics live in the API whitelist:
--   counter = summed deltas, max = high-water, min = low-water (0 = unset).
-- Spec: GAMES/livingdead/docs/GAME_NOTES/leaderboard-boards-spec.md

-- Kill matrix (player-attributed).
ALTER TABLE player_stats ADD COLUMN kills_hvz INTEGER NOT NULL DEFAULT 0; -- counter: Hunter raw
ALTER TABLE player_stats ADD COLUMN kills_hvh INTEGER NOT NULL DEFAULT 0; -- counter: Maniac
ALTER TABLE player_stats ADD COLUMN kills_zvz INTEGER NOT NULL DEFAULT 0; -- counter: Allie raw
ALTER TABLE player_stats ADD COLUMN kills_zvh INTEGER NOT NULL DEFAULT 0; -- counter: The Glutton
ALTER TABLE player_stats ADD COLUMN bat_kills INTEGER NOT NULL DEFAULT 0; -- counter: The Slugger

-- Purity boards (game-derived: pure totals or absent; max high-water).
ALTER TABLE player_stats ADD COLUMN hunter_pure_kills INTEGER NOT NULL DEFAULT 0; -- max
ALTER TABLE player_stats ADD COLUMN allie_pure_kills  INTEGER NOT NULL DEFAULT 0; -- max

-- Zombie-side + world interaction.
ALTER TABLE player_stats ADD COLUMN humans_infected INTEGER NOT NULL DEFAULT 0; -- counter: Patient Zero
ALTER TABLE player_stats ADD COLUMN chests_looted   INTEGER NOT NULL DEFAULT 0; -- counter: The Packrat
ALTER TABLE player_stats ADD COLUMN distance_m      INTEGER NOT NULL DEFAULT 0; -- counter: The Drifter (meters)

-- Economy boards.
ALTER TABLE player_stats ADD COLUMN banked_total INTEGER NOT NULL DEFAULT 0; -- counter: The Banker
ALTER TABLE player_stats ADD COLUMN died_rich    INTEGER NOT NULL DEFAULT 0; -- max: most cash lost in one death

-- Time boards.
ALTER TABLE player_stats ADD COLUMN insomniac_seconds      INTEGER NOT NULL DEFAULT 0; -- max: no-pause streak
ALTER TABLE player_stats ADD COLUMN long_walk_seconds      INTEGER NOT NULL DEFAULT 0; -- max: longest redeemed stint
ALTER TABLE player_stats ADD COLUMN kill_free_life_seconds INTEGER NOT NULL DEFAULT 0; -- max: The Ghost
ALTER TABLE player_stats ADD COLUMN lazarus_seconds        INTEGER NOT NULL DEFAULT 0; -- min: fastest redemption (0 = unset)
ALTER TABLE player_stats ADD COLUMN fastest_death_seconds  INTEGER NOT NULL DEFAULT 0; -- min: Darwin Award (0 = unset)

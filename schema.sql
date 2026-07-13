-- THE DEAD LAST — SQLite schema (reference only)
--
-- This file is NOT executed. The executable source of truth is the
-- numbered migration set in web/migrations/ (001_init.sql, 002_..., ...),
-- applied and tracked by web/bin/setup-db.php via a schema_migrations
-- table. Run `php web/bin/setup-db.php` to create/update the real
-- database. This file exists only so the schema shape is easy to read
-- without opening the migrations directory.
--
-- Current schema (mirrors migrations/001_init.sql):

-- Single userbase: this table serves BOTH the host site and the forum
-- (/bbs attaches this database and reads it as host.users — see bbs/db.php).
-- Forum profile/moderation columns were absorbed by 004_forum_user_columns.sql.
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT UNIQUE NOT NULL,
    username        TEXT UNIQUE NOT NULL,
    password_hash   TEXT NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    display_name    TEXT,                               -- forum display name; falls back to username
    bio             TEXT,                               -- forum profile blurb
    role            TEXT    NOT NULL DEFAULT 'user',    -- 'user' | 'admin' (forum admin)
    status          TEXT    NOT NULL DEFAULT 'active',  -- 'active' | 'banned'
    reputation      INTEGER NOT NULL DEFAULT 0,         -- forum reputation score
    join_date       TEXT,                               -- display join date; falls back to date(created_at)
    threads_started INTEGER NOT NULL DEFAULT 0,         -- denormalized forum counter
    chat_messages   INTEGER NOT NULL DEFAULT 0          -- denormalized forum counter
);

CREATE INDEX IF NOT EXISTS idx_users_email    ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

-- API auth tokens (issued on register/login, used by the Unity client)
CREATE TABLE IF NOT EXISTS tokens (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    token      TEXT UNIQUE NOT NULL,
    user_id    INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_tokens_token   ON tokens(token);
CREATE INDEX IF NOT EXISTS idx_tokens_user_id ON tokens(user_id);

-- Meshy backlog task events (mirrors migrations/002_meshy_tasks.sql).
-- One row per Meshy task; upserted on each webhook delivery. consumed_at is
-- set once the local meshy-queue.sh puller has downloaded the finished assets.
CREATE TABLE IF NOT EXISTS meshy_tasks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id      TEXT UNIQUE NOT NULL,
    task_type    TEXT,
    status       TEXT,
    progress     INTEGER DEFAULT 0,
    payload      TEXT NOT NULL,
    consumed_at  DATETIME,
    received_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_meshy_tasks_task_id  ON meshy_tasks(task_id);
CREATE INDEX IF NOT EXISTS idx_meshy_tasks_status   ON meshy_tasks(status);
CREATE INDEX IF NOT EXISTS idx_meshy_tasks_consumed ON meshy_tasks(consumed_at);

-- Player game stats (mirrors migrations/003_player_stats.sql; bank + lives
-- were added by 005_player_stats_bank_lives.sql).
-- One lifetime-aggregate row per user, reported by the game server through
-- the stats ingest API (api/stats/) and shown on /u/{username} profiles and
-- the leaderboard. Counters only increment; biggest_/longest_ columns take
-- max(old, new); bank is SET (game-authoritative balance).
-- See docs/player-stats.md for the stat -> game-event mapping and
-- docs/game-stats-api.md for the ingest API contract.
CREATE TABLE IF NOT EXISTS player_stats (
    user_id              INTEGER PRIMARY KEY,
    humans_killed        INTEGER NOT NULL DEFAULT 0,
    zombies_killed       INTEGER NOT NULL DEFAULT 0,
    times_turned         INTEGER NOT NULL DEFAULT 0,
    deaths               INTEGER NOT NULL DEFAULT 0,
    true_deaths          INTEGER NOT NULL DEFAULT 0,
    redemptions          INTEGER NOT NULL DEFAULT 0,
    biggest_horde_size   INTEGER NOT NULL DEFAULT 0,
    longest_life_seconds INTEGER NOT NULL DEFAULT 0,
    playtime_seconds     INTEGER NOT NULL DEFAULT 0,
    bank                 INTEGER NOT NULL DEFAULT 0,    -- set: current currency balance (005)
    lives                INTEGER NOT NULL DEFAULT 0,    -- counter: character starts/restarts (005)
    updated_at           DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Leaderboard sort indexes (ORDER BY <stat> DESC LIMIT n).
CREATE INDEX IF NOT EXISTS idx_player_stats_humans_killed  ON player_stats(humans_killed);
CREATE INDEX IF NOT EXISTS idx_player_stats_zombies_killed ON player_stats(zombies_killed);
CREATE INDEX IF NOT EXISTS idx_player_stats_biggest_horde  ON player_stats(biggest_horde_size);
CREATE INDEX IF NOT EXISTS idx_player_stats_longest_life   ON player_stats(longest_life_seconds);
CREATE INDEX IF NOT EXISTS idx_player_stats_playtime       ON player_stats(playtime_seconds);
CREATE INDEX IF NOT EXISTS idx_player_stats_bank           ON player_stats(bank);

-- Characters (mirrors migrations/006_characters.sql).
-- One row per character an account starts (permadeath: True Death ends one,
-- the player rolls a new survivor). Created via the stats ingest API
-- (api/stats/), which also increments player_stats.lives. `ref` is an
-- OPAQUE identifier minted by the game (its own convention, likely
-- username_id_name) — stored verbatim, never parsed; lookups are by numeric
-- id or exact-string ref scoped to the user.
CREATE TABLE IF NOT EXISTS characters (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,                   -- owning account
    ref        TEXT NOT NULL,                      -- opaque game-side identifier, stored verbatim
    name       TEXT,                               -- optional character name, if sent separately
    skin       TEXT NOT NULL,                      -- skin identifier the character was created with
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at   DATETIME,                           -- set when the character's run ends
    outcome    TEXT,                               -- free text for now, e.g. died | turned | true_death
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_characters_user_id ON characters(user_id);
CREATE INDEX IF NOT EXISTS idx_characters_ref     ON characters(ref);

-- Settings (mirrors migrations/007_settings.sql).
-- Generic key/value store for site/game-level configuration on the HOST
-- database. First consumers: season_start / season_end dates managed from
-- Keeper > Settings (stored as YYYY-MM-DD text; a key is deleted when
-- cleared). The forum keeps its own separate settings table in bbs/forum.db
-- (feed_sections lives there) — this table is host-level config only.
CREATE TABLE IF NOT EXISTS settings (
    key        TEXT PRIMARY KEY,                   -- setting name, e.g. season_start
    value      TEXT NOT NULL,                      -- setting value, stored as text
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP  -- last time the value was written
);

-- NPC talk-bubble messages (mirrors migrations/008_npc_messages.sql).
-- Central authoring (Keeper > Messages) of the lines generic NPCs speak in
-- overhead talk bubbles. npc_roster is the DYNAMIC spawnable-character list,
-- refreshed by Keeper's "Update roster" button which fetches the game's
-- roster endpoint (env GAME_ROSTER_URL); characters missing from a fetch are
-- marked active = 0 (kept, lines preserved). npc_messages holds the lines,
-- many per NPC; the game pulls enabled lines and picks random ones.
CREATE TABLE IF NOT EXISTS npc_roster (
    name       TEXT PRIMARY KEY,                       -- character name, e.g. "Eddie" (join key)
    gender     TEXT,                                   -- 'm' | 'f' (optional)
    role       TEXT,                                   -- 'human' | 'zombie' | 'npc' | 'base' (optional)
    height     REAL,                                   -- from the game roster (optional)
    active     INTEGER NOT NULL DEFAULT 1,             -- 1 = in latest fetched roster; 0 = gone (lines kept)
    seen_at    DATETIME DEFAULT CURRENT_TIMESTAMP      -- last roster fetch this NPC appeared in
);

CREATE TABLE IF NOT EXISTS npc_messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    npc_name   TEXT NOT NULL,                          -- FK -> npc_roster.name (the speaker)
    body       TEXT NOT NULL,                          -- the line spoken in the talk bubble
    enabled    INTEGER NOT NULL DEFAULT 1,             -- 1 = eligible; 0 = kept but muted
    weight     INTEGER NOT NULL DEFAULT 1,             -- optional relative pick weight (>=1)
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (npc_name) REFERENCES npc_roster(name) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_npc_messages_npc ON npc_messages(npc_name);

-- NOTE on admin/Keeper auth: we deliberately do NOT define a separate
-- "admins" table. Admin is just a role on the `users` row: a user with
-- `role` = 'admin' (vs 'user') and `status` = 'active' is an admin. Auth is
-- unified on the single main-site login (/login) — there is no separate
-- Keeper credential. Keeping the role on the users row (instead of a
-- dedicated table) means one login and one account list to reason about.

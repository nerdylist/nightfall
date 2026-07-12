-- THE DEAD LAST — migration 007: settings
-- Generic key/value store for site/game-level configuration on the HOST
-- database (data/graverising.sqlite). First consumers: season_start /
-- season_end dates managed from Keeper > Settings. Values are stored as
-- plain text (dates as YYYY-MM-DD); a key is removed entirely when cleared.
-- Note: the forum keeps its own separate settings table in bbs/forum.db
-- (feed_sections lives there) — this table is for host-level config only.

CREATE TABLE IF NOT EXISTS settings (
    key        TEXT PRIMARY KEY,                       -- setting name, e.g. season_start
    value      TEXT NOT NULL,                          -- setting value, stored as text
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP      -- last time the value was written
);

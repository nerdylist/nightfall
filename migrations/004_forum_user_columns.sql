-- THE DEAD LAST — migration 004: forum user columns (single userbase)
-- The forum (/bbs) previously kept its OWN users table inside bbs/forum.db,
-- with "shadow" rows linked back to this table via a tdl_user_id column.
-- That dual-userbase design is retired: THIS users table is now the one and
-- only userbase, for the site and the forum alike. This migration absorbs
-- the forum-owned profile/moderation columns; the companion data migration
-- (php bin/migrate-bbs-users.php) copies existing forum users across,
-- remaps every author/user reference in bbs/forum.db, and renames the old
-- forum users table to users_legacy.
-- Forum code reaches this table via ATTACH as host.users (see bbs/db.php).

ALTER TABLE users ADD COLUMN display_name    TEXT;                            -- shown across the forum; falls back to username when NULL/''
ALTER TABLE users ADD COLUMN bio             TEXT;                            -- profile blurb
ALTER TABLE users ADD COLUMN role            TEXT    NOT NULL DEFAULT 'user'; -- 'user' | 'admin' (forum admin)
ALTER TABLE users ADD COLUMN status          TEXT    NOT NULL DEFAULT 'active'; -- 'active' | 'banned'
ALTER TABLE users ADD COLUMN reputation      INTEGER NOT NULL DEFAULT 0;      -- forum reputation score
ALTER TABLE users ADD COLUMN join_date       TEXT;                            -- display join date; falls back to date(created_at) when NULL
ALTER TABLE users ADD COLUMN threads_started INTEGER NOT NULL DEFAULT 0;      -- denormalized counter (bbs/data/db.php keeps in sync)
ALTER TABLE users ADD COLUMN chat_messages   INTEGER NOT NULL DEFAULT 0;      -- denormalized counter (bbs/data/db.php keeps in sync)

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

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT UNIQUE NOT NULL,
    username      TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
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

-- NOTE on admin/Keeper auth: we deliberately do NOT define a separate
-- "admins" table. Keeper is a single-operator admin area, so a single
-- credential pair stored in .env (KEEPER_ADMIN_USER / KEEPER_ADMIN_PASS_HASH)
-- is simpler and sufficient. If Keeper ever needs multiple admin accounts,
-- add an `admins` table then (same shape as `users` minus game fields).

-- GRAVE RISING — SQLite schema (reference only)
-- This file is NOT executed by the prototype. It documents the intended
-- database structure for the future backend wiring pass.
--
-- NOTE on admin/Keeper auth: we deliberately do NOT define a separate
-- "admins" table for this pass. Keeper is a single-operator admin area,
-- so a single credential pair stored in .env (KEEPER_ADMIN_USER /
-- KEEPER_ADMIN_PASS_HASH) is simpler and sufficient. If Keeper ever needs
-- multiple admin accounts, add an `admins` table then (same shape as
-- `users` minus the game-specific fields).

CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    email         TEXT UNIQUE NOT NULL,
    username      TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email    ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

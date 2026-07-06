-- GRAVE RISING — migration 001: initial schema
-- Executable source of truth for the database structure.
-- Applied automatically by web/bin/setup-db.php and recorded in
-- schema_migrations. See web/schema.sql for a reference-only pointer.

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

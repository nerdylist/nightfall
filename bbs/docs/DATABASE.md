# Database (Phase 1 data layer)

The Nexus forum is now backed by a SQLite database accessed through PDO. The
pages load `data/live.php`, which returns the same `$data` array shape that
`data/mock.php` used to return — only now the data comes from the database.

`data/mock.php` is retained as the seed source and must not be deleted.

## Files

- `db.php` — `forum_db(): PDO` singleton. Opens the SQLite file, sets
  `foreign_keys = ON` and `journal_mode = WAL`, and auto-installs the schema
  if the `users` table is missing.
- `install.php` — `forum_install(PDO): array`. Creates the schema, seeds mock
  data and the admin account, returns per-table row counts. Run directly
  (`php install.php`) to install/seed manually.
- `data/db.php` — the data-access layer (DAL): query functions returning
  arrays whose shape matches `data/mock.php` exactly, with every id-type
  column explicitly cast to `(int)`.
- `data/live.php` — drop-in `$data` source backed by the DAL.

## Auto-install behavior

`db.php` auto-installs on first connect: when `forum_db()` opens the database
and the `users` table does not exist, it requires `install.php` and calls
`forum_install()`. This is silent (produces no output) and idempotent, so the
database is created and seeded transparently on the first page load.

## Schema

### users
`id INTEGER PRIMARY KEY AUTOINCREMENT`, `username TEXT UNIQUE NOT NULL`,
`email TEXT UNIQUE NOT NULL`, `password_hash TEXT NOT NULL`,
`display_name TEXT`, `bio TEXT`, `role TEXT NOT NULL DEFAULT 'user'`,
`status TEXT NOT NULL DEFAULT 'active'`, `reputation INTEGER DEFAULT 0`,
`join_date TEXT`, `threads_started INTEGER DEFAULT 0`,
`chat_messages INTEGER DEFAULT 0`, `created_at TEXT`.

### categories
`id INTEGER PRIMARY KEY`, `name TEXT NOT NULL`, `description TEXT`,
`icon TEXT`, `sort_order INTEGER DEFAULT 0`, `thread_count INTEGER DEFAULT 0`,
`post_count INTEGER DEFAULT 0`, `last_activity TEXT`, `created_at TEXT`.

### threads
`id INTEGER PRIMARY KEY`, `category_id INTEGER NOT NULL REFERENCES categories(id)`,
`author_id INTEGER NOT NULL REFERENCES users(id)`, `title TEXT NOT NULL`,
`excerpt TEXT`, `replies INTEGER DEFAULT 0`, `views INTEGER DEFAULT 0`,
`pinned INTEGER DEFAULT 0`, `locked INTEGER DEFAULT 0`, `hot INTEGER DEFAULT 0`,
`last_activity TEXT`, `created_at TEXT`, `updated_at TEXT`.

### posts
`id INTEGER PRIMARY KEY`, `thread_id INTEGER NOT NULL REFERENCES threads(id)`,
`author_id INTEGER NOT NULL REFERENCES users(id)`, `body TEXT NOT NULL`,
`created TEXT`, `created_at TEXT`.

### chat_messages
`id INTEGER PRIMARY KEY`, `thread_id INTEGER NOT NULL REFERENCES threads(id)`,
`author_id INTEGER NOT NULL REFERENCES users(id)`, `text TEXT NOT NULL`,
`timestamp TEXT`, `created_at TEXT`.

### reactions
`id INTEGER PRIMARY KEY`, `post_id INTEGER REFERENCES posts(id)`,
`user_id INTEGER NOT NULL REFERENCES users(id)`, `emoji TEXT NOT NULL`,
`created_at TEXT`, `UNIQUE(post_id, user_id, emoji)`.

### Indexes
`threads(category_id)`, `posts(thread_id)`, `chat_messages(thread_id)`,
`reactions(post_id)` — all `IF NOT EXISTS`.

## Credentials

- **Seeded mock users** all share the default password **`password123`**.
  Their emails are derived as `{username}@nexus.test`.
- **Admin account** is seeded from `.env`:
  - `ADMIN_USERNAME` (default `admin`)
  - `ADMIN_EMAIL` (default `admin@nexus.test`)
  - `ADMIN_PASSWORD` (default `changeme123`)

  The admin is created with `role = 'admin'`. **Change `ADMIN_PASSWORD` in
  `.env`** before any real deployment — the default is a placeholder.

## Resetting the database

To force a fresh re-seed, delete the database files and reload any page (which
triggers auto-install) or run `php install.php`:

```
rm -f forum.db forum.db-wal forum.db-shm
```

The `.db-wal` and `.db-shm` files are produced by WAL journal mode.

# Grave Rising — Web Backend

Plain PHP + SQLite backend for graverising.com. No framework, no Composer/npm
dependencies. Serves the public site (register/login), the Keeper admin
area, and a JSON API used by the Unity game client to register/login.

## Fresh setup

1. Copy `.env.example` to `.env` and fill in real values (`DB_PATH`,
   `SESSION_SECRET`, `KEEPER_ADMIN_USER`, `KEEPER_ADMIN_PASS_HASH`).
2. Run the DB setup/migration script:

   ```sh
   php web/bin/setup-db.php
   ```

   This creates the `data/` directory and the SQLite file if missing, then
   applies every un-applied migration in `migrations/` in order, recording
   each one in the `schema_migrations` table. Safe to re-run at any time —
   already-applied migrations are skipped, no data is lost.
3. Database is ready. Start the site (see below).

## Run locally

```sh
php -S localhost:8990 -t /Volumes/Crucial/GAMES/livingdead/web
```

Then visit `http://localhost:8990/`.

## Routing

No router. Caddy (or PHP's built-in server) serves this directory's root
directly. `/keeper`, `/api/register`, and `/api/login` work automatically
because each is a real subdirectory containing its own `index.php` —
`file_server` + `php_fastcgi` resolve them with zero extra config.

## Database / migrations

- `migrations/*.sql` — numbered migration files, the **executable source
  of truth** for the schema (`001_init.sql`, `002_...`, etc).
- `schema_migrations` table — tracks which migration files have been
  applied, so `setup-db.php` is idempotent.
- `schema.sql` — reference-only copy of the current schema shape for quick
  reading; not executed. Do not edit it independently of the migrations —
  it should always mirror the latest migration state.
- `lib/db.php` — `grave_db()` returns a shared PDO SQLite connection
  (`ERRMODE_EXCEPTION`, foreign keys on) using `DB_PATH` from `.env`.

## JSON API

`POST /api/register` and `POST /api/login` — used by the Unity game client
(UnityWebRequest) to create/authenticate accounts. See `api/register/index.php`
and `api/login/index.php` for the request/response contract. Both return
`{ "success": true, "user": {...}, "token": "..." }` on success, or
`{ "success": false, "error": "..." }` on failure.

## Keeper (admin area)

Single-operator admin login gated by `KEEPER_ADMIN_USER` /
`KEEPER_ADMIN_PASS_HASH` in `.env` (`password_verify()`), backed by a PHP
session. The dashboard lists real registered users straight from SQLite.

## Status

Fully wired: real SQLite persistence, migrations, JSON API, and the
existing UI pages (`register.php`, `login.php`, `keeper/`) all read/write
the real database.

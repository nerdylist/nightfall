# Nexus Forum

Modern, ultra-dark forum. The UI prototype is now backed by a SQLite database (Phase 1 data layer). Pages load a DB-backed `$data` array via `data/live.php`; `data/mock.php` is retained as the seed source.

Detailed docs live in docs/. Database details: see docs/DATABASE.md. Authentication details: see docs/AUTH.md. Admin area: see docs/ADMIN.md.

Phase 2 (authentication) complete: real session-based auth via `lib/auth.php` (login/register/logout, CSRF, access guards).

Phase 3 (admin area, final phase) complete: a protected `/admin/` CRUD backend for users, categories, threads, posts, chat, and reactions, gated by `require_admin()`.

## Stack

- PHP + vanilla JS + CSS, with a SQLite (PDO) data layer (see `db.php`, `install.php`, `data/db.php`, `data/live.php`).
- Served from the project ROOT via Caddy. No `/public` folder, no router.
- Site: https://forum.test/
- No third-party libraries, frameworks, or CDNs.

## Include contract

Every root-level page file follows the same composition:

1. `require __DIR__ . '/config.php';` — exposes `$CONFIG` (built from `.env`).
2. `$data = require __DIR__ . '/data/live.php';` — returns the DB-backed data array (same shape `data/mock.php` returned; `data/mock.php` is now the seed source).
3. `include __DIR__ . '/partials/head.php';` — DOCTYPE, `<head>`, stylesheet links, pre-paint theme script, opening `<body>`.
4. `include __DIR__ . '/partials/header.php';` — site header (logo, nav, search, theme switcher, user menu).
5. Page-specific `<main>` markup.
6. `include __DIR__ . '/partials/footer.php';` — footer, script tags, closing `</body></html>`.

Auth pages (`login.php`, `register.php`) include `head.php` and `header.php`, then render a centered auth card.

## Data

Pages load `data/live.php`, which returns an array with these keys: `current_user` (int id), `users`, `categories`, `threads`, `posts`, `chat_messages`. It is backed by SQLite via the DAL in `data/db.php` (which casts every id-type column to `int`). `data/mock.php` returns the same shape and is now used only as the seed source for the database. Page logic resolves and joins records in PHP. The database auto-installs on first page load. See docs/DATABASE.md.

## Theming

- Three themes: `midnight` (default), `dusk`, `light`.
- Applied via `data-theme` attribute on `<html>`, persisted in `localStorage` under key `forum-theme`.
- Palettes defined as CSS custom properties in `css/themes.css` (the only file allowed to hold raw hex values).
- A small inline pre-paint script in `partials/head.php` sets `data-theme` before first paint to avoid a flash of unstyled content (FOUC). This is the one sanctioned inline script.

## Conventions

- Relative asset paths (e.g. `css/general.css`, `js/theme.js`) — pages are served from root.
- One CSS file and one JS file per component, plus general files for shared pieces.
- No inline CSS/JS, except the pre-paint theme script in `head.php`.
- PHP partials for any reused UI section (head, header, footer, avatar, cards, rows).
- Global config via `.env` read through `config.php` (`$CONFIG`).
- Keep `.md` / `.txt` docs in `docs/`. This `CLAUDE.md` is the one allowed root doc.

# Nexus Forum — Phase 1 Notes

Phase 1 is the UI prototype: a complete, themed forum front end driven entirely by mock data, with no database and no real backend behavior.

## What was built

### 7 pages (project root)
- `index.php` — home with a hero (aggregate threads/posts/members) and a category grid.
- `category.php` — category view listing its threads (`?id=`).
- `thread.php` — thread view with the original post and a live-chat panel (`?id=`).
- `profile.php` — user profile: avatar, stats (threads, chat messages, reputation), recent threads (`?user=`).
- `settings.php` — settings forms: edit profile, theme preference, change password.
- `login.php` — log-in auth card.
- `register.php` — create-account auth card.

### Partials
- `head.php` (head + CSS links + pre-paint theme script), `header.php` (logo, nav, search, theme switcher, user menu), `footer.php` (footer + scripts).
- `avatar.php` (`render_avatar()`), `category-card.php` (tile + inline SVG icon set), `thread-row.php` (thread list row).
- Login and register intentionally omit the site header.

### Mock data
- `data/mock.php` returns `current_user`, `users`, `categories`, `threads`, `posts`, `chat_messages`. All cross-references (author_id, category_id, thread_id) point to valid ids in the same file.

### 3 themes
- `midnight` (default, ultra-dark), `dusk` (dark teal), `light`.
- Switchable via the header/settings theme switcher, persisted in `localStorage` (`forum-theme`), pre-painted in `head.php` to avoid FOUC. Palettes in `css/themes.css`.

## Known non-functional bits (by design for Phase 1)

- **Forms are inert.** Login, register, and settings forms use `action="#"` and submit nowhere. No validation, no persistence, no sessions.
- **Search is decorative.** The header search input does nothing yet.
- **Chat send is client-only.** `js/chat.js` appends the typed message to the DOM with the current user's name/initials and the current time, but it is not persisted — it disappears on reload.
- **No auth/sessions.** "Log out" is just a link to `login.php`; the current user is hard-coded as id `1` in the mock data.
- **Counts are static** strings/numbers from mock data, not computed from live records.

## Running locally

Served by Caddy at https://forum.test/ (files served from the project root, no `/public`, no router).

The `.test` host and Caddy site block are provisioned by running the `siteup` alias — the user runs `siteup`; do not run it here. Once the site is registered, open https://forum.test/ in a browser (use `-k` with curl for the local cert). Requires PHP-FPM and Caddy running via Homebrew services.

# Admin Area

## Overview

The admin area is a protected CRUD backend for the Nexus forum. Every page is gated by `require_admin()` (from `lib/auth.php`), called inside a shared bootstrap before any output is sent. Logged-out users get a 302 redirect to a login URL; logged-in non-admins get a 403.

### Logging in as admin

1. Go to https://forum.test/login.php and sign in with the admin credentials from `.env` — email **admin@nexus.test**, password **changeme123** (keys `ADMIN_EMAIL` / `ADMIN_PASSWORD`).
2. Open https://forum.test/admin/ .

The user-menu dropdown also shows an **Admin** link for admins.

## File structure

```
admin/
├── index.php            # Dashboard
├── users.php
├── user-edit.php
├── categories.php
├── category-edit.php
├── threads.php
├── thread-edit.php
├── chat.php
├── css/
│   └── admin.css
├── js/
│   └── admin.js
└── partials/
    ├── admin-bootstrap.php
    ├── admin-head.php
    ├── admin-header.php
    ├── admin-nav.php
    ├── admin-flash.php
    └── admin-footer.php
```

## Pages

### index.php — Dashboard

Six stat tiles showing `COUNT(*)` of users, categories, threads, posts, chat_messages, and reactions. Below the tiles it lists **recent signups** (the last 5 users) and **recent threads** (the last 5, joined to category and author).

### users.php — Users

Lists all users (avatar, username, display_name, email, role/status badges, reputation, join date) with an optional `?q=` search over username/display_name/email. Each row exposes actions (POST + CSRF):

- **Promote / Demote** — toggle role between `user` and `admin`
- **Ban / Unban** — toggle status between `active` and `banned`
- **Edit** — link to `user-edit.php`
- **Delete**

### user-edit.php?id=N — Edit user

Edit `display_name`, `bio`, `role`, `status`, and `reputation`.

### categories.php — Categories

List, plus create (name / description / icon / sort_order) and delete. Edit links to `category-edit.php`.

### category-edit.php?id=N — Edit category

Edit `name`, `description`, `icon`, and `sort_order`.

### threads.php — Threads

Lists all threads (joined to category and author, with replies/views and pinned/locked/hot badges), plus Edit and Delete.

### thread-edit.php?id=N — Edit thread

Edit the thread's `title`, `excerpt`, `category`, and the `pinned` / `locked` / `hot` flags. Also edit the OP (original post) body, and list and delete individual posts.

### chat.php — Chat & Reactions

Shows the last ~50 chat messages (joined to thread and author), each deletable. Includes a reactions summary (emoji counts) and a list of recent reactions, each deletable.

## Safety rules

All of the following are enforced **server-side**, not just in the UI:

- **Self-demote blocked** — an admin cannot change their own role to `user`.
- **Self-ban blocked** — an admin cannot set their own status to `banned`.
- **Self-delete blocked** — an admin cannot delete their own account.
- **Last-admin protection** — you cannot demote or delete the last remaining active admin (counted via `SELECT COUNT(*) FROM users WHERE role='admin' AND status='active'`).
- **User-delete blocked if the user has content** — foreign keys are enforced with NO cascade, so a user who authored any threads, posts, chat messages, or reactions cannot be deleted; the action is blocked with a flash error. Only users with zero content can be deleted.
- **Category-delete blocked if it has threads** — `SELECT COUNT(*) FROM threads WHERE category_id=?` must be 0.
- **Thread delete = transactional cascade** — because FKs are NO-cascade, deleting a thread runs inside a transaction that deletes children in the correct order: reactions of the thread's posts → posts → chat_messages → the thread. It rolls back on any error.
- **Post delete** — deletes the post's reactions first, then the post, in a transaction.

## Security model

- Every page requires the shared bootstrap (`admin/partials/admin-bootstrap.php`) first, which loads config + db + auth, starts the session, and calls `require_admin()` before any output.
- Every mutation is POST-only and verified with `csrf_check($_POST['csrf_token'] ?? '')` before any DB write; failures flash an error and redirect with no DB change. GET never mutates.
- **Post/Redirect/Get (PRG)** — successful mutations set a session flash (`$_SESSION['admin_flash']`) and redirect; the next GET renders the flash once and clears it.
- All SQL uses PDO prepared statements; integers are cast; role/status are validated against whitelists (`{user, admin}` / `{active, banned}`).
- All output is escaped with `adm_e()` (`htmlspecialchars`, `ENT_QUOTES`).
- Destructive actions show a JavaScript `confirm()` via `admin/js/admin.js`, driven by `data-confirm` attributes — no inline JS.

## Asset-path approach (why admin has its own partials)

The site is served from the project root, so the existing root partials (`partials/head.php`, `partials/footer.php`, `partials/header.php`) use RELATIVE asset/link paths (e.g. `css/general.css`, `index.php`) that only resolve at the root. Under `/admin/` those would 404.

So the admin area does **not** include the root partials. Instead it has its own partials under `admin/partials/` that emit the same markup but with ROOT-ABSOLUTE paths (`/css/...`, `/js/...`, `/admin/...`, `/index.php`, `/logout.php`):

- `admin-head.php` links every site stylesheet root-absolute and adds `/admin/css/admin.css` last.
- `admin-footer.php` loads `/js/theme.js`, `/js/general.js`, and `/admin/js/admin.js`.

## Styling

`admin/css/admin.css` uses ONLY the theme CSS variables from `css/themes.css` (no raw hex), matching the flat, sharp, ultra-dark three-theme design (midnight / dusk / light). It defines the admin sub-nav, stat tiles, data tables (`.admin-table`), role/status badges, flash messages, and compact action buttons.

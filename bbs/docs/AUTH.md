# Authentication (Phase 2)

Real authentication backed by SQLite users + PHP sessions. No third-party
libraries. All DB access uses prepared statements. Implemented in
`lib/auth.php`; every function is wrapped in an `if (!function_exists(...))`
guard so the file is safe to include repeatedly.

## Session & cookies

`auth_start_session()` is idempotent. If no session is active it sets cookie
params then starts the session:

- `lifetime` => 0 (session cookie)
- `path` => '/'
- `httponly` => true
- `samesite` => 'Lax'
- `secure` => true (HTTPS only — the site is served over HTTPS by Caddy)

Call it once near the top of any page that needs auth (after `config.php`,
before any output). `partials/header.php` also calls it.

## Functions and return shapes

- `auth_start_session(): void` — start session if not active. Idempotent.

- `auth_current_user(bool $forceReload = false): ?array` — returns the current
  user row or `null`. Uses a per-request static cache keyed by user id. Calling
  with `$forceReload = true` clears the cache and returns `null` (used after
  login/logout to invalidate). Returns `null` when there is no `user_id` in
  session, the row is missing, or the account `status` is `'banned'`. The
  returned array has columns: `id` (cast to int), `username`, `email`,
  `display_name`, `bio`, `role`, `status`, `reputation`, `join_date`,
  `threads_started`, `chat_messages`, `created_at`. (No `password_hash`.)

- `auth_is_logged_in(): bool` — `auth_current_user() !== null`.

- `auth_is_admin(): bool` — true only if logged in AND `role === 'admin'` AND
  `status === 'active'`.

- `auth_login(string $identifier, string $password): array` — `$identifier` may
  be email OR username (one prepared query, `email = :id OR username = :id`).
  Returns:
  - `['success' => false, 'error' => 'Invalid credentials.']` on no match or bad password
  - `['success' => false, 'error' => 'This account has been banned.']` if banned
  - `['success' => true, 'error' => null]` on success (sets `$_SESSION['user_id']`,
    regenerates the session id, invalidates the user cache)
  Assumes the session is already started.

- `auth_register(string $username, string $email, string $password, string $confirm, ?string $displayName = null): array`
  — validates and collects ALL errors. Returns:
  - `['success' => false, 'errors' => [...]]` when validation/uniqueness fails (no insert)
  - `['success' => true, 'errors' => []]` on success (inserts user, logs them in,
    regenerates session id, invalidates cache)
  Validation: required fields; username 3-30 chars `[A-Za-z0-9_]`; valid email;
  password >= 8 chars; password === confirm; username unique; email unique.
  New users get `role='user'`, `status='active'`, empty `bio`, zeroed counters,
  `join_date`/`created_at` = `date('c')`. `display_name` defaults to the username
  when not supplied.

- `auth_logout(): void` — clears `$_SESSION`, expires the session cookie,
  destroys the session, invalidates the user cache.

## CSRF flow

- `csrf_token(): string` — returns the per-session token, generating a 32-byte
  random hex token on first use.
- `csrf_field(): string` — returns a ready-to-print hidden `<input>` carrying the
  token (HTML-escaped).
- `csrf_check($token): bool` — constant-time (`hash_equals`) comparison against
  the session token.

Every POST form is CSRF-protected: **login**, **register**, and **logout** each
emit `csrf_field()` and verify with `csrf_check($_POST['csrf_token'] ?? '')`
before acting. On a failed check the auth pages show "Your session expired.
Please try again."; logout silently redirects without logging out.

## Access guards

- `require_login(): void` — if not logged in, redirects to
  `login.php?next=<urlencoded REQUEST_URI>` and exits. Used by `settings.php`.
  `login.php` validates `next` as a relative-only path (anti open-redirect)
  before redirecting back to it.
- `require_admin(): void` — if not an active admin: redirects to login (same as
  `require_login`) when not logged in, otherwise responds `403 Forbidden` and
  exits. Intended for the Phase 3 admin area (`admin/`).

## Session-aware header

`partials/header.php` calls `auth_start_session()` and `auth_current_user()`.
When a user is present it renders the user menu (Profile, Settings, an Admin
link when `auth_is_admin()`, and a CSRF-protected logout form). When no user is
present it renders "Log in" / "Register" buttons instead.

## Page integration

Content pages (`index.php`, `category.php`, `thread.php`, `profile.php`,
`settings.php`) require `lib/auth.php` and call `auth_start_session()` right
after `config.php`, then override the data array's current user:

```php
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;
```

So `$data['current_user']` is the real logged-in user id, or `0` for a guest.
`settings.php` additionally calls `require_login()` and reads `$me` directly
from `auth_current_user()` for prefilling.

## Admin credentials

Seed admin credentials are configured via `.env` (read through `config.php`),
not hardcoded.

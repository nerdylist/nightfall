# Nexus Forum — Architecture & Reference

Phase 1 UI prototype. PHP + vanilla JS + CSS, served from the project root via Caddy at https://forum.test/. No database, no router, no third-party dependencies. All dynamic content comes from a single mock-data file.

## File tree

```
forum/
├── .env                       # Global config: SITE_NAME, DEFAULT_THEME
├── CLAUDE.md                  # Root project guide (points here)
├── config.php                 # Loads .env, builds $CONFIG array
├── index.php                  # Home: hero stats + category grid
├── category.php               # Category view: thread list (?id=)
├── thread.php                 # Thread view: original post + live chat (?id=)
├── profile.php                # User profile: stats + recent threads (?user=)
├── settings.php               # Settings: profile, theme, password forms
├── login.php                  # Auth: log in (no site header)
├── register.php               # Auth: create account (no site header)
├── data/
│   └── mock.php               # Returns the full mock-data array
├── partials/
│   ├── head.php               # DOCTYPE, head, CSS links, pre-paint theme script, <body>
│   ├── header.php             # Site header: logo, nav, search, theme switcher, user menu
│   ├── footer.php             # Footer + <script> tags + </body></html>
│   ├── avatar.php             # render_avatar() helper (initials + deterministic color)
│   ├── category-card.php      # One category tile (renders the shared badge via forum_category_badge())
│   ├── category-badge.php     # Shared category badge + color helpers (forum_category_badge() / forum_category_color())
│   └── thread-row.php         # One thread list row (title, author, badges, stats)
├── css/
│   ├── themes.css             # Palettes + design tokens (ONLY file with raw hex)
│   ├── general.css            # Base/shared styles
│   ├── nav.css                # Header / nav / theme switcher / user menu
│   ├── forum.css              # Home + category grid styles
│   ├── thread.css             # Thread page styles
│   ├── chat.css               # Live chat component styles
│   ├── profile.css            # Profile page styles
│   ├── auth.css               # Login / register styles
│   └── settings.css           # Settings page styles
└── js/
    ├── theme.js               # Theme switcher: apply + persist to localStorage
    ├── general.js             # User-menu dropdown open/close behavior
    └── chat.js                # Chat composer: append message client-side
```

## Page composition (include contract)

Each root page file does the following, in order:

1. `require __DIR__ . '/config.php';` -> exposes `$CONFIG`.
2. `$data = require __DIR__ . '/data/mock.php';` -> returns the mock array.
3. (Optional) `require_once __DIR__ . '/partials/avatar.php';` if the page renders avatars.
4. (Optional) page-specific PHP to resolve the requested record from `$_GET` and join related records.
5. `include __DIR__ . '/partials/head.php';`
6. `include __DIR__ . '/partials/header.php';` — skipped on `login.php` / `register.php`.
7. Page-specific `<main>` markup; sub-partials included inside loops (`category-card.php`, `thread-row.php`).
8. `include __DIR__ . '/partials/footer.php';`

`head.php` and `header.php` defensively `require config.php` and `mock.php` if not already set, so partials can be reasoned about in isolation.

Query parameters: `category.php?id=`, `thread.php?id=`, `profile.php?user=`. All default to the first record (or current user) and fall back gracefully if the id is missing.

## Mock data shape (`data/mock.php`)

The file `return`s an associative array with these keys.

### `current_user`
Integer — the id of the logged-in user (currently `1`).

### `users[]`
- `id` (int)
- `username` (string, e.g. `devon_marsh`)
- `display_name` (string)
- `bio` (string)
- `join_date` (string, `YYYY-MM-DD`)
- `reputation` (int)
- `threads_started` (int)
- `chat_messages` (int)

### `categories[]`
- `id` (int)
- `name` (string)
- `description` (string)
- `icon` (string; one of `chat`, `megaphone`, `help`, `sparkles`, `compass`, `code`)
- `thread_count` (int)
- `post_count` (int)
- `last_activity` (string, human-readable, e.g. `2 minutes ago`)

### `threads[]`
- `id` (int)
- `category_id` (int -> `categories.id`)
- `title` (string)
- `author_id` (int -> `users.id`)
- `replies` (int)
- `views` (int)
- `last_activity` (string, human-readable)
- `pinned` (bool)
- `hot` (bool)
- `excerpt` (string)

### `posts[]`
(Original/first post per thread; no own id in mock.)
- `thread_id` (int -> `threads.id`)
- `author_id` (int -> `users.id`)
- `body` (string; `\n\n` separates paragraphs)
- `created` (string, human-readable)

### `chat_messages[]`
- `id` (int)
- `thread_id` (int -> `threads.id`)
- `author_id` (int -> `users.id`)
- `timestamp` (string, e.g. `10:42 AM`)
- `text` (string)

## Suggested Phase 2 SQLite mapping

Guidance only — adapt as needed.

- **users**: `id` PK, `username` UNIQUE, `display_name`, `bio`, `join_date`, `reputation`, `password_hash` (new), `email` (new). Counters (`threads_started`, `chat_messages`) become derived COUNT queries.
- **categories**: `id` PK, `name`, `description`, `icon`. Counters (`thread_count`, `post_count`) and `last_activity` become derived/aggregated.
- **threads**: `id` PK, `category_id` FK->categories, `author_id` FK->users, `title`, `excerpt`, `pinned` INT(0/1), `hot` INT(0/1), `created_at`. `replies`/`views`/`last_activity` derived or maintained.
- **posts**: `id` PK (new), `thread_id` FK->threads, `author_id` FK->users, `body`, `created_at`. First post per thread is the original.
- **chat_messages**: `id` PK, `thread_id` FK->threads, `author_id` FK->users, `text`, `created_at` (store real timestamp; format on render).

Replace human-readable strings (`last_activity`, `created`, `timestamp`) with real datetimes and format at render time.

## Theme palettes (`css/themes.css`)

Theme-independent tokens live on `:root`: spacing (`--space-1`..`--space-8`), radii (`--radius-sm`, `--radius`, `--radius-lg`), typography (`--font-sans`, `--fs-xs`..`--fs-3xl`, `--lh-*`, `--fw-*`), and transitions (`--transition-fast`, `--transition`, `--transition-slow`).

Each theme overrides the same set of color variables:
`--bg`, `--bg-elevated`, `--bg-card`, `--surface`, `--border`, `--border-subtle`, `--text`, `--text-muted`, `--text-faint`, `--accent`, `--accent-hover`, `--accent-soft`, `--accent-contrast`, `--shadow`, `--glow`.

Key per-theme values:

| Theme               | `--bg`    | `--accent` | `--accent-hover` |
|---------------------|-----------|------------|------------------|
| `midnight` (default)| `#0a0a0f` | `#7c5cff`  | `#8b6dff`        |
| `dusk`              | `#11161f` | `#2dd4bf`  | `#14b8a6`        |
| `light`             | `#fafafa` | `#6d4aff`  | `#5b38f0`        |

`midnight` is bound to both `[data-theme="midnight"]` and bare `:root` so it is the default even before JS runs.

## DOM hooks / contract

These selectors are the contract between markup, CSS, and JS — keep them stable.

### Theme switcher (`js/theme.js`)
- `.theme-switcher` — container (in header and on settings page).
- `.theme-option[data-theme="..."]` — clickable buttons; `data-theme` is one of `midnight` / `dusk` / `light`.
- JS applies `document.documentElement.dataset.theme`, toggles `.active` on the matching option, and persists to `localStorage` key `forum-theme`.

### User menu (`js/general.js`)
- `.user-menu` — wrapper; gets `.open` toggled on it.
- `.user-menu-trigger` — the button that toggles the dropdown.
- `.user-dropdown` — the dropdown panel (shown when `.user-menu` has `.open`).
- Closes on outside click and on `Escape`.

### Chat (`js/chat.js`)
- `.chat[data-user-name][data-user-initials]` — root; carries current user's name and initials for new lines.
- `.chat-messages` — message list container (new lines appended here, scrolled to bottom).
- `#chat-input` — text input (Enter without Shift sends).
- `#chat-send` — send button.
- New messages reuse the `.chat-line` / `.avatar` / `.chat-body` / `.chat-user` / `.chat-time` / `.chat-text` structure rendered server-side.

### Avatar helper (`partials/avatar.php`)
- `render_avatar($name, $size = 40)` — echoes a circular `.avatar` div with the user's initials and a deterministic HSL background derived from `crc32($name)`. `thread.php` mirrors the initials logic in `forum_initials()` for the chat composer.

## Phase 2 TODO

- Wire SQLite: schema per the mapping above; replace `data/mock.php` reads with queries.
- Real authentication and sessions (login/register/logout currently inert; forms `action="#"`).
- Functional chat: persist sent messages to the DB (currently client-side append only, lost on reload).
- Functional forms: settings save, new-thread/reply creation, profile edits.
- Functional search (the header search input is currently decorative).

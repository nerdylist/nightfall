# R&R: Central Admin authoring of NPC "talk bubble" messages

**Date:** 2026-07-12
**Scope:** Research & recommendation only — no code changed.
**Question:** Can we hook into NPC spawning in the game so the site knows which characters can potentially spawn, and drive their overhead "talk bubbles" from centrally-authored messages in the Keeper Admin (instead of baking them into game code)?

**Short answer:** Yes, and it's a good idea. Every piece needed already exists as a pattern to copy — on both sides. The one real gap is that the game does not currently tell the site its spawnable-character roster; there are three ways to close that, and the simplest (site owns the list) is the right place to start.

---

## 1. What the game looks like today (facts)

Unity project at `/Volumes/Crucial/GAMES/livingdead`, C# under `LivingDead/Assets/Scripts/`, namespace `Nightfall`. Web base URL constant: `NetworkConfig.WebBaseUrl = "https://graverising.com"`.

### Spawnable roster IS data-driven and discoverable
- Single source of truth: **`LivingDead/Assets/Resources/CharacterRoster.json`**, loaded at runtime by `CharacterRoster.LoadFromResources()`.
- Each entry: `{ name, gender, role, height }` where `role ∈ base | human | npc | zombie`.
- Current roster (the actual characters that can appear):
  - **Humans / townsfolk (they wander around):** Eddie, Bella, Dr-mason
  - **Zombies:** Donna, Dave
  - **NPC (stalker):** Zeek
  - **base** (Johnny, Barbra) = animation source rigs / character-select, not ambient spawns.
- Adding a character = a new roster JSON entry + scene rebuild. **No code changes.**

### Who spawns them
- `NPC/HumanSpawnDirector.cs` — spawns the townsfolk (`HumanNPC`), filtered to `role == "human"`. **These are the natural "speakers"** — they roam via a hand-rolled wander AI (no NavMesh).
- `Zombies/ZombieSpawnDirector.cs` — spawns zombies (`ZombieAI`).
- `NPC/StalkerEncounter.cs` — the single Zeek.
- Spawns rent from a `SkinnedBodyPool` (not runtime `Instantiate`). The join key is `SkinEntry.skinName` (== roster `name`).

### NPC identity (matters for targeting)
- The character name is encoded in the GameObject name: `"Human_" + skinName + "_" + counter` (e.g. `Human_Eddie_3`).
- **`HumanNPC` does NOT store its roster name as a field** — only the GameObject name carries it. Robust per-character targeting would want a one-line identity field added at the spawn site (`HumanSpawnDirector.Spawn`, ~L347). Behavior gating is available via `HumanNPC.CurrentMode` (`Roam | Fight | Flee`).

### No talk-bubble system exists yet — but the world-space UI pattern does
- No speech/dialogue/nametag/overhead-text system today.
- Two proven, self-contained, camera-billboarded world-space-Canvas components to copy:
  - **`Combat/FloatingHealthBar.cs`** — production pattern: static `Show(GameObject target, ...)`, builds its own world-space Canvas anchored to the CharacterController capsule top + head clearance, billboards in `LateUpdate`. **The head-anchor math is already solved.**
  - **`Debug/AnimLabel.cs`** — essentially a talk bubble already: floating `Text` on a dark panel above a head. A bubble is a productionized version of this.
- Note: these use Unity UI `Text`/`Image`, not TextMeshPro.

### How the game already talks to the network
- Pattern in use: **`UnityWebRequest` inside a coroutine + `JsonUtility`**, carrying the session token from `Net/SessionState.cs`. Template to copy: `UI/LoginModal.cs` → `LoginRequest` coroutine.
- **There is no recurring poll loop yet.** A talk-bubble feed would introduce the first one — an idiomatic small `MonoBehaviour` whose coroutine hits `WebBaseUrl + "/api/messages"` on an interval.

---

## 2. What the site looks like today (facts)

Site at `/Volumes/Crucial/SITES/thedeadlast` (PHP + SQLite, codename "GRAVE RISING").

### There's already a proven game→site→Keeper pipeline
"Game POSTs data up via an authenticated API → it lands in the host DB → Keeper reads the host DB and renders a table." This is exactly the shape we need, and it already runs for player stats and characters.

- **Ingest API:** `api/stats/index.php`. Auth = `Authorization: Bearer <GAME_API_KEY>` verified with `hash_equals` (fail-closed on empty env). It writes `player_stats` and `characters` (the game already *registers its spawned characters up* here, as opaque `{ref, skin, name}` rows).
- **Pull API precedent:** `api/feed/index.php` — GET, bounded `limit` (`max(1, min(20, $limit))`), prepared `ORDER BY created_at DESC LIMIT ?`, explicit per-row public projection. **This is the template for an endpoint that serves messages TO the game.**
- **Ack/consume precedent:** `api/meshy/index.php` accepts `{ "consume": [ids...] }` and stamps `consumed_at` — maps directly onto "mark a message delivered / rotate it."
- **Shared conventions:** `require config.php` (+ `api/_respond.php`), `header('Content-Type: application/json')`, explicit method guard, `grave_read_json_input()` in, `grave_json_response(int $status, array $body): never` out with a `{ success, ... }` envelope, `grave_db()` handle. Each endpoint needs one `.htaccess` `RewriteRule`. No CORS (server-to-server).

### Keeper CRUD is a well-worn skeleton
`keeper/settings.php` is the template a new `keeper/messages.php` copies verbatim in shape:
- Guard: `if (!grave_is_admin()) { redirect to /login }`.
- POST-redirect-GET with a Keeper-scoped CSRF token (`$_SESSION['keeper_csrf']`, `hash_equals` check).
- Flash via `$_SESSION['keeper_flash']` (write in POST, read-and-clear on GET).
- Upsert helper idiom: `INSERT ... ON CONFLICT(...) DO UPDATE SET ... `, prepared statements only, empty value → DELETE.
- UI vocabulary: `.card`/`.keeper-table-card`, `.keeper-table`, `.field`, `.btn`, `.keeper-flash`, a per-page `css/keeper-messages.css`.
- Nav: add one `<a class="keeper-header__link">Messages</a>` to `partials/keeper-header.php`.
- New table: `migrations/008_messages.sql` mirrored into `schema.sql`, applied via the web-runnable `migrate.php?token=…`.

### The one real gap
The game does **not** currently push up an authoritative "these are the characters that can spawn" list. It only reports characters *after a player spawns them* (opaque per-run `characters` rows with a `skin` string). There is no NPC roster, no spawnable catalog, and no messages/talk_bubble table on the site yet.

---

## 3. Recommended architecture

A **pull model**: Keeper authors messages → they sit in a host-DB table → the game polls a read endpoint on an interval and speaks them. This mirrors `api/feed` (pull) + `keeper/settings.php` (author) + `api/stats` (auth), so it's almost entirely copy-existing-patterns.

```
Keeper Admin (keeper/messages.php)         Game (Unity)
  │  authors/edits messages                  │
  ▼                                          │  every N sec, coroutine:
host DB  ── messages table ──►  GET /api/messages?...   ◄─ Bearer GAME_API_KEY
  ▲                                          │      returns a bounded list
  └──────────── grave_db() ──────────────────┘      NPC speaks via a new
                                                     TalkBubble billboard
```

### Data model (`migrations/008_messages.sql`, mirrored in `schema.sql`)
A `messages` table, e.g.:
- `id` (PK)
- `body` (the line spoken)
- `target` (nullable) — which character/role it's for; NULL = any speaker
- `weight` / `enabled` — for weighting & toggling without deleting
- `starts_at` / `ends_at` (nullable) — optional scheduling window (enables "interesting stuff": seasonal/event lines)
- `created_at`, `updated_at`

This gives you the "room to do interesting stuff": weighting, enable/disable, scheduling windows, and per-character targeting — all authored centrally, none baked into the game.

### The "which characters can spawn" question — 3 options, start simple
1. **Site owns the list (recommended first step).** Keeper's target dropdown is a small site-authored list (a `settings` key or tiny `message_targets` table), seeded with today's roster (Eddie, Bella, Dr-mason, Donna, Dave, Zeek) + the `role` values. Zero game changes. Mirrors how `feed_sections` is a site-authored list. Downside: you hand-maintain it when the roster changes.
2. **Derive from reported data.** Populate the dropdown from `SELECT DISTINCT skin FROM characters`. Game-provided, but only reflects skins actually *played*, and skins ≠ a curated NPC roster. Weak fit.
3. **Game registers its real roster (the "hook into spawning" ask, done properly).** Add a tiny game→site call that POSTs `CharacterRoster.json` (or the directors' live `skins` lists) up to a new `roster` table, following the exact `api/stats` + migration + `grave_db()` pattern; Keeper reads it like `keeper/meshy.php` reads `meshy_tasks`. This is the only option that gives Keeper a game-truth spawnable list. It's a small game-side addition, and every piece of the pattern already exists to copy.

**My take:** ship **Option 1** with the CRUD + pull endpoint first — it delivers the whole feature (centrally-authored bubbles streaming into the game) with **no game-code risk**. Add **Option 3** later if/when keeping the target list in sync by hand becomes annoying; it's a clean, additive follow-up using the same ingest pattern, and it's the literal answer to "hook into spawning so the site knows the roster."

### Game-side work (the only genuinely new code)
Two small additions, both modeled on existing files:
1. A **`TalkBubble`** world-space billboard component — copy `FloatingHealthBar`/`AnimLabel`, add a `Text` child, `Show(GameObject target, string line)`. Attach lazily to `HumanNPC` (and optionally `ZombieAI`).
2. A **message-poll `MonoBehaviour`** — copy `LoginModal.LoginRequest`'s `UnityWebRequest` + `JsonUtility` approach; on an interval, GET `/api/messages`, cache the list, and have wandering NPCs occasionally pop a bubble (gated on `HumanNPC.CurrentMode == Roam`). If per-character targeting is used, add the one-line identity field at the spawn site so an NPC knows its own roster name.

Everything on the **site side** (migration, `keeper/messages.php`, `api/messages`, `.htaccess` rule, nav link, per-page CSS) is a direct copy of existing patterns and carries essentially no novel risk.

---

## 4. Effort / risk summary

| Piece | Where | Basis | Risk |
|---|---|---|---|
| `messages` table + migration | site | copy `007_settings.sql` | low |
| `keeper/messages.php` CRUD | site | copy `keeper/settings.php` | low |
| `api/messages` (GET pull) | site | copy `api/feed` + `api/stats` auth | low |
| Nav link, per-page CSS | site | copy existing | trivial |
| `TalkBubble` billboard | game | copy `FloatingHealthBar` / `AnimLabel` | medium (new UI, needs in-engine tuning) |
| Message-poll behaviour | game | copy `LoginModal.LoginRequest` | medium (first poll loop; interval/perf tuning) |
| NPC identity field (only if per-char targeting) | game | 1 line at spawn site | low |
| Game-truth roster ingest (Option 3, optional/later) | both | copy `api/stats` + `characters` pattern | low–medium |

**Bottom line:** the idea is sound and well-supported by both codebases. Recommend building it site-first (Option 1) so you get the full centrally-authored talk-bubble stream with no game-code changes beyond the two new game components, and treat the game-truth roster registration as a clean optional follow-up.

---

### Key file references
**Game:** `Assets/Resources/CharacterRoster.json`, `Scripts/CharacterRoster.cs`, `Scripts/NPC/HumanSpawnDirector.cs` (spawn ~L229-368), `Scripts/NPC/HumanNPC.cs`, `Scripts/Zombies/ZombieSpawnDirector.cs`, `Scripts/Combat/FloatingHealthBar.cs`, `Scripts/Debug/AnimLabel.cs`, `Scripts/UI/LoginModal.cs` (LoginRequest ~L377-431), `Scripts/Net/NetworkConfig.cs`, `Scripts/Net/SessionState.cs`.
**Site:** `api/stats/index.php` (auth 68-87), `api/feed/index.php`, `api/meshy/index.php` (consume 53-74), `api/_respond.php`, `lib/db.php`, `keeper/settings.php` (CSRF 96-100, POST handler 111-213, save helper 67-82), `partials/keeper-header.php` (nav 22-33), `migrations/007_settings.sql`, `schema.sql`, `migrate.php`, `.htaccess` (api routing 8-14), `.env` (`GAME_API_KEY`).

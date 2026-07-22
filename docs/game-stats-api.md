# Game Stats API — game → site integration

How THE DEAD LAST (Unity) reports player stats and characters to the
website so they show on `/u/{username}` profiles and the leaderboard.
Self-contained; nothing else is required reading (stat design background:
`player-stats.md`).

## Endpoint

| | |
| --- | --- |
| Local dev | `https://thedeadlast.test/api/stats` |
| Production | `https://thedeadlast.com/api/stats` |
| Methods | `POST` (report stats / character events), `GET` (read back stats) |
| Auth | `Authorization: Bearer <GAME_API_KEY>` on every request |
| Key location | site `.env`, `GAME_API_KEY=...` (server-to-server key — keep it on the game **server**, never in the shipped client) |
| Content type | `application/json` |

The `username` in every call is the player's site account username
(`users.username` — the same account name they registered/log in with).

## POST — report stats

```json
{
  "username": "maria",
  "stats": { "<column>": <non-negative int>, ... }
}
```

`stats` can carry any subset of the columns below — batch several events
into one request whenever possible (e.g. flush on death / session end).

### Columns and how the server applies them

| Column | Applied as | Send |
| --- | --- | --- |
| `humans_killed` | `stored + sent` | delta (kills since last report) |
| `zombies_killed` | `stored + sent` | delta |
| `times_turned` | `stored + sent` | delta |
| `deaths` | `stored + sent` | delta (every death, incl. true deaths) |
| `true_deaths` | `stored + sent` | delta (permadeaths only; also count in `deaths`) |
| `redemptions` | `stored + sent` | delta |
| `lives` | `stored + sent` | delta — but see Characters: creating a character adds 1 automatically, so normally never send this |
| `playtime_seconds` | `stored + sent` | delta (seconds since last flush) |
| `biggest_horde_size` | `max(stored, sent)` | current/peak horde size — safe to spam, never lowers |
| `longest_life_seconds` | `max(stored, sent)` | the finished character's lifetime in seconds |
| `bank` | `sent` (replaces) | the CURRENT balance — not a delta |

All values must be integers >= 0. Counters are deltas: sending
`{"deaths": 1}` twice records 2 deaths — do not resend totals.

### Success response (200)

The full, post-update row comes back so the game can reconcile:

```json
{
  "success": true,
  "username": "maria",
  "applied": { "humans_killed": 2 },
  "stats": {
    "user_id": 9,
    "humans_killed": 14, "zombies_killed": 55, "times_turned": 2,
    "deaths": 3, "true_deaths": 1, "redemptions": 0,
    "biggest_horde_size": 37, "longest_life_seconds": 7420,
    "playtime_seconds": 86100, "bank": 1500, "lives": 4,
    "updated_at": "2026-07-11 13:09:45"
  }
}
```

## Characters

Each account plays many characters over time (permadeath). The site keeps
one row per character: numeric `id` (assigned by the site), your `ref`,
optional `name`, `skin`, `started_at`, `ended_at`, `outcome`.

**`ref` is opaque to the server.** Send any non-empty string — it is
stored verbatim (spaces, underscores, emoji, whatever) and never parsed or
validated structurally. The format is the game's own convention (e.g.
`{username}_{id}_{name}`); the parser lives on the game side, TBD. Keep
refs unique per account — lookups are exact-string equality scoped to the
account, and if a ref is reused the newest character wins.

### Create a character

```json
{
  "username": "maria",
  "character": { "ref": "maria_17_Ironjaw", "skin": "farmer_f", "name": "Ironjaw" }
}
```

`ref` and `skin` required, `name` optional. Creating a character
**automatically increments `lives`** (a new character IS a new life —
don't also send `lives: 1`). Response includes the numeric id — store it:

```json
{
  "success": true,
  "username": "maria",
  "character": { "id": 17, "ref": "maria_17_Ironjaw", "name": "Ironjaw",
                 "skin": "farmer_f", "started_at": "2026-07-11 13:09:45",
                 "ended_at": null, "outcome": null },
  "character_id": 17,
  "stats": { ... }
}
```

### End a character

Reference by numeric `id` or exact `ref`; `outcome` is optional free text
(suggested: `died`, `turned`, `true_death`):

```json
{
  "username": "maria",
  "character": { "id": 17, "ended": true, "outcome": "true_death" }
}
```

Stamps `ended_at` (first end wins; re-ending doesn't move the timestamp)
and returns the updated character.

### Character context on stat posts (optional)

A stats POST may include `"character_id": 17` **or**
`"character_ref": "maria_17_Ironjaw"`. The server validates the character
belongs to the user (404 if not) and echoes it back as
`character_context`. Per-character stat aggregation is not implemented
yet — aggregates are account-level for now.

A single POST can carry `character` and `stats` together (e.g. end the
character and flush its final stats in one call).

## Example calls per event

`BASE=https://thedeadlast.test` (use `-k` locally for the self-signed cert);
`KEY` = the `GAME_API_KEY` value.

```bash
# New life — character created (also bumps lives automatically)
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","character":{"ref":"maria_17_Ironjaw","skin":"farmer_f","name":"Ironjaw"}}'

# Player killed a human
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","character_id":17,"stats":{"humans_killed":1}}'

# Player killed a zombie
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"zombies_killed":1}}'

# Player got infected and turned
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"times_turned":1}}'

# Ordinary death
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"deaths":1,"longest_life_seconds":7420}}'

# True Death — end the character and flush final stats in one call
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","character":{"id":17,"ended":true,"outcome":"true_death"},"stats":{"deaths":1,"true_deaths":1,"longest_life_seconds":7420}}'

# Horde high-water mark (send whenever the horde grows; never lowers)
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"biggest_horde_size":37}}'

# Bank sync — CURRENT balance, replaces the stored value
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"bank":1500}}'

# Session end / periodic flush — batch everything since the last flush
curl -k -X POST "$BASE/api/stats" -H "Authorization: Bearer $KEY" \
  -d '{"username":"maria","stats":{"playtime_seconds":3600,"humans_killed":2,"zombies_killed":1,"biggest_horde_size":37,"bank":1500}}'
```

## GET — read back current stats

```bash
curl -k "$BASE/api/stats?username=maria" -H "Authorization: Bearer $KEY"
```

Returns `{ "success": true, "username": ..., "stats": { ... } }` (all
zeros if the player has never reported).

## Errors

All errors are JSON: `{ "success": false, "error": "..." }`.

| Status | When |
| --- | --- |
| 401 | Missing/wrong `Authorization: Bearer` token |
| 404 | `username` doesn't match a site account, or the referenced character doesn't exist / belongs to another account |
| 400 | Missing `username`; neither `stats` nor `character` present; unknown stat key (response includes `valid_keys`); a value that isn't a non-negative integer; character create without `ref`/`skin`; character end without `id`/`ref` |
| 405 | Any method other than POST/GET |

On any non-200, nothing was written — safe to retry the whole request
(but remember counters are deltas: only retry sends that failed).

## Daily playtime buckets (leaderboard metric — added 2026-07-22)

The leaderboard ranks characters by ACTIVE survival time logged per real
24-hour day (game docs: season-clock-and-leaderboards.md). A stats POST
with character context (or an inline character create/end) may include:

```json
"daily_playtime": [ { "date": "2026-08-02", "seconds": 7200 }, ... ]
```

- Absolute per-day totals (NOT deltas). Server upserts per (character, date)
  with seconds = MAX(stored, sent) — resends are idempotent and monotonic.
- Max 40 buckets per post; date = strict YYYY-MM-DD; seconds = int >= 0.
- Applied buckets echo back as "applied_playtime".

### GET /api/leaderboard (public, no auth)

```json
{ "success": true,
  "season": { ...same shape as /api/season + "server_date" },
  "top":   [ { "rank": 1, "character": { "id", "name", "skin", "outcome",
               "started_at" }, "username": "maria", "seconds": 20000 }, ... ],
  "today": [ ...same row shape, today's bucket only... ] }
```

`top` sums buckets inside the season window (all-time when no season is
set), dead and living characters both, limit 25. Site page: /leaderboard.

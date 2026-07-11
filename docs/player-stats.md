# Player Stats — game → site mapping

Lifetime aggregate stats for THE DEAD LAST, stored on the site DB
(`data/graverising.sqlite`) in one `player_stats` row per account
(`user_id` → `users.id`). The game server reports stats against the
account's **username**; the site resolves `username → users.id` and
upserts the row. Created by `migrations/003_player_stats.sql`, mirrored
in `schema.sql`.

Displayed on `/u/{username}` profiles and, later, the leaderboard.

## The stats

| Stat | Column | Type | Semantics | Game event → update |
| --- | --- | --- | --- | --- |
| Total Humans Killed | `humans_killed` | INTEGER | counter | Player lands the killing blow on a human — zombie bite-kill / group-feed kill (`PlayerZombieAttack`, the path that fires `PlayerTransformation.VoidHumanity`), or human-vs-human PvP kill via `PlayerCombat`. → `+1` |
| Total Zombies Killed | `zombies_killed` | INTEGER | counter | Player kills a zombie — human weapons (`PlayerCombat` → `ZombieHealth.Die`) or zombie-vs-zombie bite kill (`PlayerZombieAttack.TryBiteZombie` → `KilledZombie`). → `+1` |
| Total Times Turned | `times_turned` | INTEGER | counter | Infection completes and the human transforms into a zombie (`PlayerTransformation` turn / `HudController.EnterZombieMode`). A bite alone is infection, not a turn. → `+1` |
| Total Deaths | `deaths` | INTEGER | counter | Any character death: `PlayerHealth.Die` (PULSE to 0) or zombie death via damage/starvation short of redemption (`PlayerTransformation.ResolveRageZero` death branches). Includes true deaths. → `+1` |
| Total True Deaths | `true_deaths` | INTEGER | counter | Permadeath only — the two documented True Death paths: human eaten (brain/devoured) or the player-zombie killed by anyone. Character deleted. → `+1` (and `deaths +1`) |
| Total Redemptions | `redemptions` | INTEGER | counter | Zombie starves Rage to 0 at full HUMAN, never voided → redemption scene, rises human (`PlayerTransformation.ResolveRageZero` redemption branch). → `+1` |
| Biggest Horde Size | `biggest_horde_size` | INTEGER | max | Horde membership count changes while the player leads/belongs to a horde (`PlayerHorde` register/unregister). → `max(stored, reported)` |
| Longest Survival Time | `longest_life_seconds` | INTEGER | max (seconds) | On any character end (death, true death, or turn closes the *human* stint — measured spawn → end of that character's life). → `max(stored, reported)` |
| Total Playtime | `playtime_seconds` | INTEGER | counter (seconds) | Session end / periodic flush: seconds played this session across all characters. → `+= reported` |
| Bank | `bank` | INTEGER | set | Currency balance changes (periodic sync / session flush). The game owns the balance, so the site just stores the latest value. → `= reported` |
| Lives | `lives` | INTEGER | counter | A character is started — the first spawn and every restart after a True Death (the "new survivor" flow). Incremented automatically when the game creates a character through the ingest API. → `+1` |

`bank` and `lives` were added by `migrations/005_player_stats_bank_lives.sql`.
`updated_at` is set to `CURRENT_TIMESTAMP` on every ingest write.

Rationale for the four columns beyond the requested five: True Death vs
ordinary death, redemption, and survival-as-the-core-loop are all explicit,
documented mechanics (game `CLAUDE.md`: True Death, Redemption Runners;
`docs/meters.md`: `ResolveRageZero` outcomes), and playtime falls out of the
same session reporting for free. Nothing speculative (no XP, loot, or
mutation stats — those systems aren't final).

## Update rules

- **Counters** (`humans_killed`, `zombies_killed`, `times_turned`, `deaths`,
  `true_deaths`, `redemptions`, `playtime_seconds`, `lives`): increment only —
  `SET col = col + :delta`. Never decremented; permadeath wipes the
  character, not the account's lifetime tally.
- **Maxes** (`biggest_horde_size`, `longest_life_seconds`): high-water marks —
  `SET col = max(col, :value)`.
- **Set** (`bank`): last write wins — `SET col = :value`. The game is the
  authority on the balance; the site never adds to it.
- Row creation: `INSERT ... ON CONFLICT(user_id) DO UPDATE` so the first
  report for an account creates the row.

## Characters

Each account plays many characters over time — permadeath means every True
Death ends one and the player rolls a new survivor. Alongside the aggregate
`player_stats` row, the site keeps one `characters` row per character
(`migrations/006_characters.sql`):

- `id` — numeric id assigned by the site, returned to the game on create.
- `ref` — an **opaque** identifier string minted by the game (its own
  convention, likely `username_id_name`). Stored verbatim, never parsed or
  validated structurally; lookups are exact-string equality scoped to the
  user. Parser TBD on the game side.
- `name` (optional), `skin` (required) — which character/skin was played.
- `started_at`, `ended_at`, `outcome` — lifecycle; `outcome` is free text
  for now (e.g. `died` / `turned` / `true_death`), deliberately not
  over-modeled.

Creating a character through the API **automatically increments `lives`** —
a new character IS a new life, so the game never reports `lives` directly.
Per-character stat columns can come later; user-level aggregates stay in
`player_stats`. Stat reports may carry a `character_id`/`character_ref`
context, which is validated (must belong to the user) but not yet
aggregated per character.

## Leaderboard queries (later)

Each board is a single ordered scan, e.g.:

```sql
SELECT u.username, s.humans_killed
FROM player_stats s JOIN users u ON u.id = s.user_id
ORDER BY s.humans_killed DESC
LIMIT 25;
```

Migrations 003/005 create indexes on the sortable columns so these are index
walks instead of full-table sorts: `humans_killed`, `zombies_killed`,
`biggest_horde_size`, `longest_life_seconds`, `playtime_seconds`, `bank`.
(`times_turned` / `deaths` / `true_deaths` / `redemptions` / `lives` can get
indexes later if they become boards.)

## Ingest API

Built: `POST /api/stats` (stat reports + character create/end) and a `GET`
read-back, served by `api/stats/index.php`, authenticated with the `.env`
`GAME_API_KEY` bearer token. Full contract, examples, and error responses
live in [game-stats-api.md](game-stats-api.md) — that file is the handoff
doc for the game-side integration.

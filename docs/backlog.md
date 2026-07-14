# Backlog

Deferred items noted during development. Not scheduled — grab when ready.

## Rarity tiers ingest (noted 2026-07-14)
Rarity is becoming a tier system in the game (tiers carry attributes that scale/modify item damage, value, etc. — not just a label). The game dev will **bundle the rarity tiers into the existing `/api/items` response** (a top-level array alongside `items`, e.g. `"rarities": [...]`). Once that ships:
- Look at the actual tier shape in the response (fields TBD by the game — likely multipliers, colour, sort/rank, drop-weight, or flat modifiers).
- Add a `rarity_tiers` table (columns matching those fields) + mirror in `schema.sql`.
- Ingest tiers alongside items in the Keeper > Items **Update** handler (`keeper/items.php`).
- Add a small tiers editor in Keeper (view/edit, same pattern as items), and optionally drive the item rarity field off the tier list.
- Do NOT build the table until the endpoint shape exists — building against a guess = rework.

## Admin (Keeper) full-screen revamp — Phase 2: fold in bbs/admin (noted 2026-07-14)
Phase 1 (done): rebuilt the Keeper shell — full-screen, left sidebar (game logo), black & white, collapsible on mobile. The sidebar already MOCKS a "Forum" nav group pointing at the future consolidated locations.
Phase 2 (this item): migrate the forum-admin pages into Keeper under the new shell and retire `bbs/admin/`:
- Pages to move: `bbs/admin/{categories,category-edit,threads,thread-edit,chat}.php` (+ the `users`/`user-edit` stubs, already redirects). ~850 lines total.
- Reconcile the two conventions: `bbs/admin/partials/*` (admin-bootstrap/nav/footer/flash + its own CSRF `csrf_token()`) vs `partials/keeper-*` (keeper_csrf). Pick keeper's.
- Auth already unified (`grave_is_admin()` / `require_admin()`), so it's mostly re-housing + re-styling the moderation forms into the keeper shell + swapping CSRF.

## Admin panel consolidation (noted 2026-07-11)
Merge the two admin panels into one living at `/keeper/`:
- Either fold the bbs admin (`bbs/admin/` — forum dashboard, threads, thread-edit, chat moderation) into the existing Keeper panel, or repurpose the bbs admin UI as the base and integrate Keeper's features (dashboard, Forum Users, Meshy, Settings/feed sections, season dates) into it — but it lives at `/keeper/` either way.
- Auth is already unified enough to make this straightforward: single userbase (host `users`), and a keeper session already satisfies the bbs admin guard (`require_admin()` keeper bypass).
- Watch out for the two CSRF token schemes (`keeper_csrf` vs the bbs admin one) and the two page-layout/partial conventions (`partials/keeper-*.php` vs `bbs/admin/partials/*`) — pick one of each.

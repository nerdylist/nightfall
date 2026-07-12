# Backlog

Deferred items noted during development. Not scheduled — grab when ready.

## Admin panel consolidation (noted 2026-07-11)
Merge the two admin panels into one living at `/keeper/`:
- Either fold the bbs admin (`bbs/admin/` — forum dashboard, threads, thread-edit, chat moderation) into the existing Keeper panel, or repurpose the bbs admin UI as the base and integrate Keeper's features (dashboard, Forum Users, Meshy, Settings/feed sections, season dates) into it — but it lives at `/keeper/` either way.
- Auth is already unified enough to make this straightforward: single userbase (host `users`), and a keeper session already satisfies the bbs admin guard (`require_admin()` keeper bypass).
- Watch out for the two CSRF token schemes (`keeper_csrf` vs the bbs admin one) and the two page-layout/partial conventions (`partials/keeper-*.php` vs `bbs/admin/partials/*`) — pick one of each.

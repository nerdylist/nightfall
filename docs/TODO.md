# TODO — current work

The site is mostly stable. Remaining work is largely **adding content**; the
open engineering items below are game-side animation issues captured here as the
working list.

_Last updated: 2026-07-25._

## Animation fixes (game-side — Unity)

These live in the game (`/Volumes/Crucial/GAMES/livingdead`); tracked here as
the current punch list.

- [ ] **3P jump overshoot.** In third-person, the character jump lands **too far
  ahead** of where it should, then **snaps back** to the correct position.
  Reads as a visual rubber-band — likely a root-motion vs. controller-position
  mismatch (animation drives forward displacement that the controller then
  corrects). Reconcile root motion / in-air horizontal handling so the landing
  spot matches the jump arc, no snap-back.
- [ ] **Zombie walk.** Zombie walk animation looks wrong / off — needs
  adjusting.
- [ ] **Die animations.** The "die" animations need adjusting.

## Content (ongoing)

- [ ] Populate the **Characters** roster (Keeper → Characters): Humans, NPCs,
  Zombies, Enemies — with avatar/pose art and per-character talk-bubble lines.
- [ ] General content passes as the game and site fill out.

## Notes

- Reference docs moved to `docs/archive/`. Live API contracts kept at `docs/`
  root: `game-stats-api.md`, `player-stats.md`.

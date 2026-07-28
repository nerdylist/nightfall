-- THE DEAD LAST — migration 016: character wave-scaling dials (Boss 2026-07-28).
-- Site-authored balance dials the game reads at boot via /api/characters (same
-- runtime-config pattern as the roster + talk messages). Tuning difficulty must
-- never require a game build. Applies to spawner NPCs only — never the player's
-- own survivor. See the game repo: docs/ROADMAP/wave-scaling-handoff.md.
--
-- EVERY field is OPTIONAL and nullable: a character with none of these set
-- behaves exactly like today (game falls back to its baked HP, wave_min 1,
-- wave_max null, no growth). So this migration is purely additive/non-breaking.

ALTER TABLE characters ADD COLUMN hp_base   INTEGER;          -- starting HP at first eligible wave (null = game's baked HP)
ALTER TABLE characters ADD COLUMN wave_min  INTEGER;          -- never spawns before this wave (null -> game default 1)
ALTER TABLE characters ADD COLUMN wave_max  INTEGER;          -- never spawns after this wave (null = forever)
ALTER TABLE characters ADD COLUMN hp_cap    INTEGER;          -- hard HP ceiling regardless of band math (null = none)
ALTER TABLE characters ADD COLUMN hp_bands  TEXT;             -- JSON array of { from, to|null, mode:"pct"|"flat", value } (null/[] = no growth)

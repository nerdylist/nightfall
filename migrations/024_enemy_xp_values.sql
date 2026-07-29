-- THE DEAD LAST — migration 024: xp_value for the seeded enemies (Boss/Nancy 2026-07-29).
-- Migration 021 seeded the 12 enemy spawners before the xp_value column existed
-- (023), so they'd all fall back to the game default (50). Set their kill-XP per
-- Nancy's sketch: shamblers 50 · midgame 80 · heavies (Franky/Zeek-z) 400 ·
-- machines (X5/Z7) 600. Only fills rows whose xp_value is still unset, so a
-- later Keeper edit is never overwritten. Matched by (name, type).

-- Shamblers (baseline horde) — 50.
UPDATE characters SET xp_value = 50
 WHERE type = 'Zombie' AND name IN ('Eddie','Dottie','Chad','Shirley') AND xp_value IS NULL;

-- Mid-game bodies — 80.
UPDATE characters SET xp_value = 80
 WHERE type = 'Zombie' AND name IN ('Janet','Eric','Mel') AND xp_value IS NULL;

-- Heavies — 400 (turned clown Zeek + the stitched giant Franky).
UPDATE characters SET xp_value = 400
 WHERE ((type = 'Zombie' AND name = 'Zeek') OR (type = 'NPC' AND name = 'Franky'))
   AND xp_value IS NULL;

-- Machines — 600.
UPDATE characters SET xp_value = 600
 WHERE type = 'NPC' AND name IN ('X5','Z7') AND xp_value IS NULL;

-- Scripted stalker Zeek (NPC) — a rare, dangerous kill; 400.
UPDATE characters SET xp_value = 400
 WHERE type = 'NPC' AND name = 'Zeek' AND xp_value IS NULL;

-- THE DEAD LAST — migration 021: seed enemy-spawner wave dials (Boss/Nancy 2026-07-29).
-- Seeds the ENEMY spawners (Zombies + NPCs) from the game's designed defaults
-- (docs/ROADMAP/wave-dials-defaults.json), each with hp_base/wave_min/hp_cap and
-- hp_bands carrying per-band spawn_weight + max_alive (wave-scaling-handoff §2/§7).
--
-- HUMANS ARE EXCLUDED: humans don't wave-scale — they level up via items/buffs, a
-- separate future system. Only Zombie + NPC rows are seeded here.
--
-- Idempotent: each INSERT is guarded by NOT EXISTS on (name,type), so re-running
-- never duplicates, and it won't touch a matching row already authored in Keeper.

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Eddie', 'Zombie', 1, 80, 1, 3000, '[{"from":1,"to":10,"mode":"pct","value":3,"spawn_weight":100,"max_alive":8},{"from":11,"to":30,"mode":"pct","value":4,"spawn_weight":90,"max_alive":10},{"from":31,"to":60,"mode":"pct","value":2,"spawn_weight":70,"max_alive":12},{"from":61,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":14}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Eddie' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Dottie', 'Zombie', 1, 75, 1, 3000, '[{"from":1,"to":10,"mode":"pct","value":3,"spawn_weight":100,"max_alive":8},{"from":11,"to":30,"mode":"pct","value":4,"spawn_weight":90,"max_alive":10},{"from":31,"to":60,"mode":"pct","value":2,"spawn_weight":70,"max_alive":12},{"from":61,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":14}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Dottie' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Chad', 'Zombie', 1, 90, 1, 3500, '[{"from":1,"to":10,"mode":"pct","value":3,"spawn_weight":100,"max_alive":8},{"from":11,"to":30,"mode":"pct","value":4,"spawn_weight":90,"max_alive":10},{"from":31,"to":60,"mode":"pct","value":2,"spawn_weight":70,"max_alive":12},{"from":61,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":14}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Chad' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Shirley', 'Zombie', 1, 70, 1, 3000, '[{"from":1,"to":10,"mode":"pct","value":3,"spawn_weight":100,"max_alive":8},{"from":11,"to":30,"mode":"pct","value":4,"spawn_weight":90,"max_alive":10},{"from":31,"to":60,"mode":"pct","value":2,"spawn_weight":70,"max_alive":12},{"from":61,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":14}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Shirley' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Janet', 'Zombie', 1, 100, 8, 4000, '[{"from":8,"to":20,"mode":"pct","value":6,"spawn_weight":30,"max_alive":3},{"from":21,"to":45,"mode":"pct","value":4,"spawn_weight":60,"max_alive":6},{"from":46,"to":80,"mode":"pct","value":2,"spawn_weight":70,"max_alive":8},{"from":81,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":9}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Janet' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Eric', 'Zombie', 1, 110, 8, 4000, '[{"from":8,"to":20,"mode":"pct","value":6,"spawn_weight":30,"max_alive":3},{"from":21,"to":45,"mode":"pct","value":4,"spawn_weight":60,"max_alive":6},{"from":46,"to":80,"mode":"pct","value":2,"spawn_weight":70,"max_alive":8},{"from":81,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":9}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Eric' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Mel', 'Zombie', 1, 150, 10, 5000, '[{"from":8,"to":20,"mode":"pct","value":6,"spawn_weight":30,"max_alive":3},{"from":21,"to":45,"mode":"pct","value":4,"spawn_weight":60,"max_alive":6},{"from":46,"to":80,"mode":"pct","value":2,"spawn_weight":70,"max_alive":8},{"from":81,"to":null,"mode":"pct","value":1,"spawn_weight":60,"max_alive":9}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Mel' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Zeek', 'Zombie', 1, 130, 12, 5000, '[{"from":15,"to":30,"mode":"pct","value":8,"spawn_weight":5,"max_alive":1},{"from":31,"to":60,"mode":"pct","value":5,"spawn_weight":18,"max_alive":3},{"from":61,"to":100,"mode":"pct","value":2,"spawn_weight":30,"max_alive":5},{"from":101,"to":null,"mode":"pct","value":1,"spawn_weight":35,"max_alive":6}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Zeek' AND type = 'Zombie');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Franky', 'NPC', 1, 300, 15, 12000, '[{"from":15,"to":30,"mode":"pct","value":8,"spawn_weight":5,"max_alive":1},{"from":31,"to":60,"mode":"pct","value":5,"spawn_weight":18,"max_alive":3},{"from":61,"to":100,"mode":"pct","value":2,"spawn_weight":30,"max_alive":5},{"from":101,"to":null,"mode":"pct","value":1,"spawn_weight":35,"max_alive":6}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Franky' AND type = 'NPC');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'X5', 'NPC', 1, 350, 25, 15000, '[{"from":25,"to":50,"mode":"pct","value":7,"spawn_weight":4,"max_alive":1},{"from":51,"to":90,"mode":"pct","value":4,"spawn_weight":12,"max_alive":2},{"from":91,"to":null,"mode":"pct","value":1,"spawn_weight":20,"max_alive":4}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'X5' AND type = 'NPC');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Z7', 'NPC', 1, 350, 25, 15000, '[{"from":25,"to":50,"mode":"pct","value":7,"spawn_weight":4,"max_alive":1},{"from":51,"to":90,"mode":"pct","value":4,"spawn_weight":12,"max_alive":2},{"from":91,"to":null,"mode":"pct","value":1,"spawn_weight":20,"max_alive":4}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Z7' AND type = 'NPC');

INSERT INTO characters (name, type, active, hp_base, wave_min, hp_cap, hp_bands)
SELECT 'Zeek', 'NPC', 1, 200, 1, 6000, '[{"from":1,"to":null,"mode":"pct","value":1,"spawn_weight":100,"max_alive":1}]'
WHERE NOT EXISTS (SELECT 1 FROM characters WHERE name = 'Zeek' AND type = 'NPC');


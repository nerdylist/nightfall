-- THE DEAD LAST — migration 017: full item offload to runtime (Boss 2026-07-28).
-- The site becomes the source of truth for the item catalog — metadata, weapon
-- stats, wave dials — the same way it already owns the character roster. The
-- game hydrates /api/items at boot (falls back to its cache, then baked
-- registrations). See the game repo: docs/ROADMAP/loot-runtime-handoff.md.
--
-- Mirrors the characters pattern (migration 016): scalar columns for the simple
-- dials, JSON columns for the flexible/nested blocks (weapon stats, damage
-- bands). Every field is OPTIONAL/nullable — an item with none of these set is
-- byte-identical to the pre-offload catalog row.

-- Runtime identity / visual binding.
ALTER TABLE items ADD COLUMN visual_key TEXT;               -- baked prefab key (e.g. "revolver"); null -> game resolves by id/fallback
ALTER TABLE items ADD COLUMN active INTEGER NOT NULL DEFAULT 1; -- in the runtime feed when 1

-- Weapon balance block — the full FirearmSpec, stored as JSON, present only for
-- weapons. Emitted as the nested "weapon" object. { ammo_id, capacity, damage,
-- pellets, spread_deg, range, reload_seconds, cooldown, noise_radius, recoil_deg,
-- flash_intensity, flash_seconds, crit_chance, crit_damage }
ALTER TABLE items ADD COLUMN weapon_json TEXT;

-- Wave-scaling dials — same semantics as characters (016 / wave-scaling-handoff).
ALTER TABLE items ADD COLUMN wave_min      INTEGER;         -- not in the loot pool before this wave (null -> game default 1)
ALTER TABLE items ADD COLUMN wave_max      INTEGER;         -- drops out of the pool after this wave (null = forever)
ALTER TABLE items ADD COLUMN rarity_weight INTEGER;         -- relative weight in the loot roll (null = game default)
ALTER TABLE items ADD COLUMN damage_bands  TEXT;            -- JSON array of { from, to|null, mode:"pct"|"flat", value } (null/[] = no growth)

CREATE INDEX IF NOT EXISTS idx_items_active ON items(active);

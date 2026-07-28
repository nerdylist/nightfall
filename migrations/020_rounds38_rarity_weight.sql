-- THE DEAD LAST — migration 020: rounds_38 must never roll as loot (Boss/Nancy 2026-07-28).
-- The seed import (before the offload upsert carried rarity_weight) created
-- rounds_38 with NULL weight, so it would roll as junk loot. It is the canonical
-- ammo the revolver reloads from — "invisible plumbing" — and must stay in the
-- catalog but never drop. Force its loot weight to 0. Idempotent; only touches
-- rounds_38, and only if it exists.
UPDATE items SET rarity_weight = 0
 WHERE item_id = 'rounds_38' AND (rarity_weight IS NULL);

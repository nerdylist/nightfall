-- THE DEAD LAST — migration 019: apply committed item icons (Boss 2026-07-28).
-- Sets items.icon_path for each item whose art was committed under assets/items/
-- (filename = item_id). Idempotent + safe: an UPDATE for an item_id that doesn't
-- exist affects 0 rows, and only rows WITHOUT an icon are touched, so a later
-- Keeper-uploaded icon is never overwritten by a re-run.

UPDATE items SET icon_path = 'assets/items/ammo_pistol.png' WHERE item_id = 'ammo_pistol' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/axe.png' WHERE item_id = 'axe' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/baseball_bat.png' WHERE item_id = 'baseball_bat' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/bbe.png' WHERE item_id = 'bbe' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/canned_fish.png' WHERE item_id = 'canned_fish' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/chainsaw.png' WHERE item_id = 'chainsaw' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/cola.png' WHERE item_id = 'cola' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/combat_knife.png' WHERE item_id = 'combat_knife' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/flashlight.png' WHERE item_id = 'flashlight' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/fortune-cookie.png' WHERE item_id = 'fortune-cookie' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/key.png' WHERE item_id = 'key' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/luck-rabbitsfoot.png' WHERE item_id = 'luck-rabbitsfoot' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/medkit.png' WHERE item_id = 'medkit' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/money_one.png' WHERE item_id = 'money_one' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/money_stack.png' WHERE item_id = 'money_stack' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/potion-holywater.png' WHERE item_id = 'potion-holywater' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/potion-rage.png' WHERE item_id = 'potion-rage' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/potion-shadow.png' WHERE item_id = 'potion-shadow' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/revolver.png' WHERE item_id = 'revolver' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/shotgun.png' WHERE item_id = 'shotgun' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/shotgun_shells.png' WHERE item_id = 'shotgun_shells' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/slugger.png' WHERE item_id = 'slugger' AND (icon_path IS NULL OR icon_path = '');
UPDATE items SET icon_path = 'assets/items/wallet.png' WHERE item_id = 'wallet' AND (icon_path IS NULL OR icon_path = '');


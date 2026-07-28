-- THE DEAD LAST — migration 018: item icon column + seed the item catalog
-- (Boss 2026-07-28). Adds items.icon_path (uploaded item art, same concept
-- as character avatars) and seeds the 22-item catalog Nancy exported
-- (docs/ROADMAP/item-seed.json). Idempotent: INSERT OR IGNORE by item_id, so
-- re-running never duplicates or clobbers site-authored rows.

-- Uploaded item icon (assets/items/…); null = none. Mirrors characters.avatar_path.
ALTER TABLE items ADD COLUMN icon_path TEXT;

INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('combat_knife', 'Combat Knife', 'Weapon', '1', '5', 'combat_knife', '1', '{"damage":30,"range":3,"cooldown":0.45}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('baseball_bat', 'Baseball Bat', 'Weapon', '1', '5', 'baseball_bat', '1', '{"damage":32,"range":3.2,"cooldown":0.7,"crit_chance":0.2,"crit_damage":150}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('revolver', 'Revolver', 'Weapon', '1', '5', 'revolver', '1', '{"ammo_id":"rounds_38","capacity":6,"damage":40,"pellets":1,"spread_deg":0,"range":91,"reload_seconds":0.9,"cooldown":0.4,"noise_radius":45,"recoil_deg":1.2,"flash_intensity":3,"flash_seconds":0.06,"crit_chance":0.2,"crit_damage":200}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('rounds_38', '.38 Rounds', 'Ammo', '1', '5', 'rounds_38', '1', NULL, '0');
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('flashlight', 'Flashlight', 'Light', '1', '5', 'flashlight', '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('shotgun', 'Shotgun', 'Weapon', '1', '5', 'shotgun', '1', '{"ammo_id":"shotgun_shells","capacity":2,"damage":55,"pellets":8,"spread_deg":3,"range":9,"reload_seconds":1.4,"cooldown":0.7,"noise_radius":55,"recoil_deg":2.5,"flash_intensity":5,"flash_seconds":0.09,"crit_chance":0.3,"crit_damage":200}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('shotgun_shells', 'Shotgun Shells', 'Ammo', '1', '5', 'shotgun_shells', '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('chainsaw', 'Chainsaw', 'Weapon', '1', '5', 'chainsaw', '1', '{"damage":45,"range":3.4,"cooldown":0.9,"noise_radius":30,"crit_chance":0.4,"crit_damage":200}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('ammo_pistol', 'Ammo Pistol', 'Ammo', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('canned_fish', 'Canned Fish', 'Food', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('cola', 'Cola', 'Food', '1', '5', 'cola', '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('medkit', 'Medkit', 'Medicine', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('axe', 'Axe', 'Weapon', '1', '5', NULL, '1', '{"damage":36,"range":3.2,"cooldown":0.7,"crit_chance":0.3,"crit_damage":100}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('key', 'Key', 'Tool', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('wallet', 'Wallet', 'Material', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('slugger', 'THE SLUGGER', 'Weapon', '0', '1', 'slugger', '1', '{"damage":38,"range":3.4,"cooldown":0.65,"crit_chance":0.25,"crit_damage":200}', NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('bbe', 'BIG BRAIN ENERGY', 'Medicine', '1', '5', 'bbe', '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('fortune-cookie', 'FORTUNE COOKIE', 'Medicine', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('luck-rabbitsfoot', 'RABBIT''S FOOT', 'Medicine', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('potion-holywater', 'HOLY WATER', 'Medicine', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('potion-rage', 'FURY BREW', 'Medicine', '1', '5', NULL, '1', NULL, NULL);
INSERT OR IGNORE INTO items (item_id, display_name, category, stackable, max_stack, visual_key, active, weapon_json, rarity_weight) VALUES ('potion-shadow', 'SHADOW DRAUGHT', 'Medicine', '1', '5', NULL, '1', NULL, NULL);

CREATE INDEX IF NOT EXISTS idx_items_category ON items(category);

<?php
/**
 * THE DEAD LAST — API: in-game item catalog.
 *
 * The SITE owns the item catalog (full runtime offload — see the game repo:
 * docs/ROADMAP/loot-runtime-handoff.md). The game hydrates this at boot, falls
 * back to its cache, then to baked registrations. Authoring is in Keeper.
 *
 * GET  /api/items                      (public, read-only)
 *   -> { "success": true, "count": N, "items": [ item, ... ] }
 *   Each item (offload shape; legacy metadata kept alongside for compatibility):
 *     id, name, category, stackable, max_stack        (always)
 *     icon_url                                         (absolute URL to uploaded item art; absent if none)
 *     visual_key, active                               (offload; active bool)
 *     weapon: { ammo_id, capacity, damage, pellets, spread_deg, range,
 *               reload_seconds, cooldown, noise_radius, recoil_deg,
 *               flash_intensity, flash_seconds, crit_chance, crit_damage }
 *                                                      (present only for weapons)
 *     wave_min, wave_max, rarity_weight                (wave dials; absent when unset)
 *     damage_bands: [ { from, to|null, mode:"pct"|"flat", value }, ... ]
 *                                                      (absent when no growth)
 *     + legacy: item_id, display_name, rarity, power, weight_kg, value,
 *               durability, description, used_to, thumbnail, model, extra
 *   Active-only by default; ?all=1 includes inactive (authoring/preview).
 *   Keys are ABSENT when unset (never null-as-signal), except `to` inside a
 *   band which is null for an open-ended tail. Same conventions as the
 *   character feed, so the read side is symmetrical.
 *
 * POST /api/items                      (Bearer GAME_API_KEY)  — SEED IMPORT
 *   body { "items": [ { "item_id"|"id": "...", ...metadata..., "weapon": {…}? } ] }
 *   NON-DESTRUCTIVE upsert by item_id (no catalog wipe). Seeds/updates the
 *   game-metadata columns; NEVER overwrites site-authored columns (visual_key,
 *   active, weapon_json, wave_min/max, rarity_weight, damage_bands) on rows
 *   that already exist. Nancy runs this once to seed the site from ItemDatabase
 *   + the firearm table; after that, authoring is in Keeper.
 *   -> { "success": true, "imported": N }
 *
 * Server-to-server key; keep GAME_API_KEY out of the shipped client.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET' && $method !== 'POST') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

/** Known item columns (everything else on a posted item -> the `extra` blob). */
const ITEM_COLUMNS = [
    'item_id', 'display_name', 'category', 'rarity', 'stackable', 'max_stack',
    'power', 'weight_kg', 'value', 'durability', 'description', 'used_to',
    'thumbnail', 'model',
];

/** Verify Authorization: Bearer <GAME_API_KEY> (same pattern as api/stats). */
function items_verify_bearer(): bool
{
    $secret = trim((string) env('GAME_API_KEY', ''));
    if ($secret === '') {
        return false;
    }
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
        return $token !== '' && hash_equals($secret, $token);
    }
    return false;
}

try {
    $db = grave_db();
} catch (PDOException $e) {
    grave_json_response(500, ['success' => false, 'error' => 'Database error.']);
}

// -------------------------------------------------------------------------
// GET — public read
// -------------------------------------------------------------------------
if ($method === 'GET') {
    // Offload columns (migration 017) are optional — select them only if
    // present so an un-migrated DB still serves the base catalog.
    $cols = [];
    try {
        foreach ($db->query('PRAGMA table_info(items)') as $ci) {
            $cols[$ci['name']] = true;
        }
    } catch (PDOException $e) {
        grave_json_response(500, ['success' => false,
            'error' => 'Items table unavailable (run migrations).']);
    }
    $hasOffload = isset($cols['visual_key'], $cols['active'], $cols['weapon_json'],
        $cols['wave_min'], $cols['wave_max'], $cols['rarity_weight'], $cols['damage_bands']);
    $hasIcon = isset($cols['icon_path']);

    $base = 'item_id, display_name, category, rarity, stackable, max_stack,
             power, weight_kg, value, durability, description, used_to,
             thumbnail, model, extra';
    if ($hasOffload) {
        $base .= ', visual_key, active, weapon_json, wave_min, wave_max,
                   rarity_weight, damage_bands';
    }
    if ($hasIcon) {
        $base .= ', icon_path';
    }

    // Active-only by default (the live runtime feed); ?all=1 for authoring.
    $where = ($hasOffload && empty($_GET['all'])) ? ' WHERE active = 1' : '';

    try {
        $rows = $db->query(
            "SELECT {$base} FROM items{$where} ORDER BY category, item_id"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        grave_json_response(500, ['success' => false,
            'error' => 'Items table unavailable (run migrations).']);
    }

    $items = [];
    foreach ($rows as $r) {
        // §2 offload shape (id/name/…); legacy metadata kept alongside so any
        // current consumer still resolves. Keys absent when unset.
        $it = [
            'id'           => $r['item_id'],
            'name'         => $r['display_name'],
            'category'     => $r['category'],
            'stackable'    => (bool) $r['stackable'],
            'max_stack'    => (int) $r['max_stack'],
            // legacy / extra metadata (unchanged consumers)
            'item_id'      => $r['item_id'],
            'display_name' => $r['display_name'],
            'rarity'       => $r['rarity'],
            'power'        => $r['power'] === null ? null : (int) $r['power'],
            'weight_kg'    => $r['weight_kg'] === null ? null : (float) $r['weight_kg'],
            'value'        => $r['value'] === null ? null : (int) $r['value'],
            'durability'   => $r['durability'] === null ? null : (int) $r['durability'],
            'description'  => $r['description'],
            'used_to'      => $r['used_to'],
            'thumbnail'    => $r['thumbnail'],
            'model'        => $r['model'],
            'extra'        => $r['extra'] ? json_decode($r['extra'], true) : null,
        ];

        if ($hasIcon && !empty($r['icon_path'])) {
            $it['icon_url'] = grave_asset_abs_url((string) $r['icon_path']);
        }

        if ($hasOffload) {
            $it['active'] = (int) $r['active'] === 1;
            if (!empty($r['visual_key'])) { $it['visual_key'] = $r['visual_key']; }

            // Weapon block — present only for weapons (weapon_json set).
            if (!empty($r['weapon_json'])) {
                $w = json_decode((string) $r['weapon_json'], true);
                if (is_array($w) && $w) { $it['weapon'] = $w; }
            }

            // Wave dials — only the keys that are set.
            if ($r['wave_min']      !== null) { $it['wave_min']      = (int) $r['wave_min']; }
            if ($r['wave_max']      !== null) { $it['wave_max']      = (int) $r['wave_max']; }
            if ($r['rarity_weight'] !== null) { $it['rarity_weight'] = (int) $r['rarity_weight']; }
            if (!empty($r['damage_bands'])) {
                $b = json_decode((string) $r['damage_bands'], true);
                if (is_array($b) && $b) { $it['damage_bands'] = $b; }
            }
        }

        $items[] = $it;
    }

    grave_json_response(200, [
        'success' => true,
        'count'   => count($items),
        'items'   => $items,
    ]);
}

// -------------------------------------------------------------------------
// POST — SEED IMPORT (upsert by item_id; non-destructive)
// -------------------------------------------------------------------------
// The site now OWNS the item catalog (full offload — see the game repo:
// docs/ROADMAP/loot-runtime-handoff.md), so this is no longer a "replace the
// whole catalog" wipe. It's a one-time/occasional seed importer: Nancy exports
// ItemDatabase + firearm specs as JSON to populate the site, then authoring
// happens in Keeper. It UPSERTS the game-metadata columns by item_id and NEVER
// touches the site-authored columns (visual_key, active, weapon_json, wave_min,
// wave_max, rarity_weight, damage_bands) on rows that already exist.
if (!items_verify_bearer()) {
    grave_json_response(401, ['success' => false, 'error' => 'Unauthorized.']);
}

$input = grave_read_json_input();
$list = $input['items'] ?? null;
if (!is_array($list)) {
    grave_json_response(400, ['success' => false, 'error' => 'Missing "items" array.']);
}

// If the payload's weapon block should seed weapon_json too, accept a nested
// "weapon" object per item (offload shape). Only used on INSERT of a new item;
// existing weapon_json is preserved.
try {
    $db->beginTransaction();

    $sql = 'INSERT INTO items
        (item_id, display_name, category, rarity, stackable, max_stack, power,
         weight_kg, value, durability, description, used_to, thumbnail, model,
         extra, weapon_json, visual_key, wave_min, wave_max, rarity_weight,
         damage_bands, updated_at)
        VALUES
        (:item_id, :display_name, :category, :rarity, :stackable, :max_stack, :power,
         :weight_kg, :value, :durability, :description, :used_to, :thumbnail, :model,
         :extra, :weapon_json, :visual_key, :wave_min, :wave_max, :rarity_weight,
         :damage_bands, CURRENT_TIMESTAMP)
        ON CONFLICT(item_id) DO UPDATE SET
            display_name = excluded.display_name,
            category     = excluded.category,
            rarity       = excluded.rarity,
            stackable    = excluded.stackable,
            max_stack    = excluded.max_stack,
            power        = excluded.power,
            weight_kg    = excluded.weight_kg,
            value        = excluded.value,
            durability   = excluded.durability,
            description  = excluded.description,
            used_to      = excluded.used_to,
            thumbnail    = excluded.thumbnail,
            model        = excluded.model,
            extra        = excluded.extra,
            -- Offload fields: seed them on update WHEN the payload carries one,
            -- but never WIPE a site-authored value when the payload omits it.
            -- The game export sends these, so a re-import fills pre-existing
            -- rows; a payload without them keeps what is stored. (excluded.*
            -- is NULL when the key is omitted, so COALESCE keeps the stored
            -- value.)
            weapon_json   = COALESCE(excluded.weapon_json, weapon_json),
            visual_key    = COALESCE(excluded.visual_key, visual_key),
            wave_min      = COALESCE(excluded.wave_min, wave_min),
            wave_max      = COALESCE(excluded.wave_max, wave_max),
            rarity_weight = COALESCE(excluded.rarity_weight, rarity_weight),
            damage_bands  = COALESCE(excluded.damage_bands, damage_bands),
            updated_at    = CURRENT_TIMESTAMP';
            // NOTE: `active` and `icon_path` are intentionally NOT carried on
            // update — active defaults to 1 on insert and is a site toggle;
            // icons are authored/committed on the site. weapon_json + wave
            // dials ARE carried (COALESCE) so a seed export can populate them on
            // rows that already existed without clobbering later Keeper edits.
    $stmt = $db->prepare($sql);

    $imported = 0;
    // Reserved keys that are columns/handled explicitly (not folded into extra).
    $reserved = array_merge(ITEM_COLUMNS, ['id', 'name', 'weapon', 'visual_key',
        'active', 'wave_min', 'wave_max', 'rarity_weight', 'damage_bands']);

    foreach ($list as $item) {
        // Accept the offload shape ("id") OR the legacy shape ("item_id").
        // (The seed export and the GET feed both use "id".)
        $itemId = '';
        if (is_array($item)) {
            $itemId = (string) ($item['item_id'] ?? $item['id'] ?? '');
        }
        if ($itemId === '') {
            continue; // skip malformed rows (no id)
        }

        // Fold any genuinely-unknown keys into the extra blob.
        $extra = [];
        foreach ($item as $k => $v) {
            if (!in_array($k, $reserved, true)) {
                $extra[$k] = $v;
            }
        }

        // Weapon block seeds weapon_json; null when the item has no weapon.
        $weaponJson = null;
        if (isset($item['weapon']) && is_array($item['weapon']) && $item['weapon']) {
            $weaponJson = json_encode($item['weapon'], JSON_UNESCAPED_SLASHES);
        }
        // Damage bands (offload) — accept a nested array; null when absent.
        $damageBands = null;
        if (isset($item['damage_bands']) && is_array($item['damage_bands']) && $item['damage_bands']) {
            $damageBands = json_encode($item['damage_bands'], JSON_UNESCAPED_SLASHES);
        }

        $stmt->execute([
            'item_id'      => $itemId,
            'display_name' => (string) ($item['display_name'] ?? $item['name'] ?? $itemId),
            'category'     => $item['category']    ?? null,
            'rarity'       => $item['rarity']      ?? null,
            'stackable'    => !empty($item['stackable']) ? 1 : 0,
            'max_stack'    => isset($item['max_stack']) ? (int) $item['max_stack'] : 1,
            'power'        => isset($item['power'])      ? (int) $item['power'] : null,
            'weight_kg'    => isset($item['weight_kg'])  ? (float) $item['weight_kg'] : null,
            'value'        => isset($item['value'])      ? (int) $item['value'] : null,
            'durability'   => isset($item['durability']) ? (int) $item['durability'] : null,
            'description'  => $item['description'] ?? null,
            'used_to'      => $item['used_to']     ?? null,
            'thumbnail'    => $item['thumbnail']   ?? null,
            'model'        => $item['model']       ?? null,
            'extra'        => $extra ? json_encode($extra) : null,
            'weapon_json'  => $weaponJson,
            'visual_key'   => (isset($item['visual_key']) && $item['visual_key'] !== '') ? (string) $item['visual_key'] : null,
            'wave_min'     => isset($item['wave_min']) ? (int) $item['wave_min'] : null,
            'wave_max'     => isset($item['wave_max']) ? (int) $item['wave_max'] : null,
            'rarity_weight'=> isset($item['rarity_weight']) ? (int) $item['rarity_weight'] : null,
            'damage_bands' => $damageBands,
        ]);
        $imported++;
    }

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    grave_json_response(500, ['success' => false, 'error' => 'Write failed.']);
}

grave_json_response(200, ['success' => true, 'imported' => $imported]);

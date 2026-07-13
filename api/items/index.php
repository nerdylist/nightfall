<?php
/**
 * THE DEAD LAST — API: in-game item catalog.
 *
 * GET  /api/items                      (public, read-only)
 *   -> { "success": true, "count": N, "items": [ { item_id, display_name,
 *        category, rarity, stackable, max_stack, power, weight_kg, value,
 *        durability, description, used_to, thumbnail, model, extra }, ... ] }
 *   Lists every item in the game (mirror of the C# ItemDatabase). Used while
 *   authoring item thumbnails / to see what's registered. DYNAMIC — it reflects
 *   whatever the game last exported, and grows as items are added.
 *
 * POST /api/items                      (Bearer GAME_API_KEY)
 *   body { "items": [ { "item_id": "...", "display_name": "...", ...any of the
 *          columns... }, ... ] }
 *   REPLACES the whole catalog (delete-all + insert) so the table always matches
 *   the game's current registry. Run the game's editor export after adding
 *   items. Unknown keys per item are folded into the `extra` JSON blob, so the
 *   game can add fields without changing this endpoint.
 *   -> { "success": true, "replaced": N }
 *
 * Server-to-server key; keep GAME_API_KEY out of the shipped client (the export
 * is an editor/build-time push, not a runtime call).
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
    try {
        $rows = $db->query(
            'SELECT item_id, display_name, category, rarity, stackable, max_stack,
                    power, weight_kg, value, durability, description, used_to,
                    thumbnail, model, extra
             FROM items ORDER BY category, item_id'
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist yet (migrations not run) — report cleanly.
        grave_json_response(500, ['success' => false,
            'error' => 'Items table unavailable (run migrations).']);
    }

    $items = [];
    foreach ($rows as $r) {
        $items[] = [
            'item_id'      => $r['item_id'],
            'display_name' => $r['display_name'],
            'category'     => $r['category'],
            'rarity'       => $r['rarity'],
            'stackable'    => (bool) $r['stackable'],
            'max_stack'    => (int) $r['max_stack'],
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
    }

    grave_json_response(200, [
        'success' => true,
        'count'   => count($items),
        'items'   => $items,
    ]);
}

// -------------------------------------------------------------------------
// POST — game export ingest (replace the whole catalog)
// -------------------------------------------------------------------------
if (!items_verify_bearer()) {
    grave_json_response(401, ['success' => false, 'error' => 'Unauthorized.']);
}

$input = grave_read_json_input();
$list = $input['items'] ?? null;
if (!is_array($list)) {
    grave_json_response(400, ['success' => false, 'error' => 'Missing "items" array.']);
}

try {
    $db->beginTransaction();
    $db->exec('DELETE FROM items');

    $sql = 'INSERT INTO items
        (item_id, display_name, category, rarity, stackable, max_stack, power,
         weight_kg, value, durability, description, used_to, thumbnail, model,
         extra, updated_at)
        VALUES
        (:item_id, :display_name, :category, :rarity, :stackable, :max_stack, :power,
         :weight_kg, :value, :durability, :description, :used_to, :thumbnail, :model,
         :extra, CURRENT_TIMESTAMP)';
    $stmt = $db->prepare($sql);

    $inserted = 0;
    foreach ($list as $item) {
        if (!is_array($item) || empty($item['item_id'])) {
            continue; // skip malformed rows
        }

        // Fold any non-column keys into the extra blob.
        $extra = [];
        foreach ($item as $k => $v) {
            if (!in_array($k, ITEM_COLUMNS, true)) {
                $extra[$k] = $v;
            }
        }

        $stmt->execute([
            'item_id'      => (string) $item['item_id'],
            'display_name' => (string) ($item['display_name'] ?? $item['item_id']),
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
        ]);
        $inserted++;
    }

    $db->commit();
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    grave_json_response(500, ['success' => false, 'error' => 'Write failed.']);
}

grave_json_response(200, ['success' => true, 'replaced' => $inserted]);

<?php
/**
 * THE DEAD LAST — API: character roster feed (runtime).
 *
 * GET /api/characters                      (public, read-only)
 *   -> { "success": true, "count": N, "characters": [ { id, name, age, gender,
 *        type, description, avatar, pose, messages,
 *        <wave dials if set> }, ... ] }
 *   The ACTIVE characters authored in Keeper (Characters tab). This is the
 *   canonical list piped into the game's runtime startup — the characters
 *   running in the world. Inactive characters are excluded. Ordered by
 *   type, then the admin's sort order, then name.
 *
 *   Optional: ?type=Human|NPC|Zombie|Enemy filters to one type.
 *   Optional: ?all=1 includes inactive rows too (for authoring/preview).
 *
 * WAVE-SCALING DIALS (site-authored balance, migration 016; see the game repo
 * docs/ROADMAP/wave-scaling-handoff.md). Emitted flat and ONLY when set, so a
 * character with no dials is byte-identical to before and the game applies its
 * own fallbacks:
 *   hp_base  (int)  starting HP at the first eligible wave
 *   wave_min (int)  never spawns before this wave
 *   wave_max (int)  never spawns after this wave (absent = forever)
 *   hp_cap   (int)  hard HP ceiling regardless of band math
 *   hp_bands (array) [ { from, to|null, mode:"pct"|"flat", value,
 *                        spawn_weight?, max_alive? }, ... ]
 *                    per wave range: HP growth (pct compounds / flat adds per
 *                    wave) + optional spawn mix — spawn_weight (relative pick
 *                    likelihood, default 100) and max_alive (ceiling of this
 *                    character alive at once). Both keys absent = today's
 *                    behavior. See the game repo wave-scaling-handoff.md §7.
 *
 * Image fields are absolute web paths under /assets/characters/ (or null).
 * Read-only; authoring happens in Keeper, so there is no POST here.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$db = grave_db();

// Table may not exist yet on a DB that hasn't run migration 014.
try {
    $has = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='characters'")->fetchColumn();
} catch (Throwable $e) {
    $has = false;
}
if ($has === false) {
    grave_json_response(200, ['success' => true, 'count' => 0, 'characters' => []]);
}

$where = [];
$params = [];

// Active-only by default; ?all=1 returns everything (authoring/preview).
if (empty($_GET['all'])) {
    $where[] = 'active = 1';
}

// Optional type filter.
$type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
if ($type !== '' && in_array($type, ['Human', 'NPC', 'Zombie', 'Enemy'], true)) {
    $where[] = 'type = :type';
    $params[':type'] = $type;
}

// Wave-scaling dials (migration 016) are optional columns — only select them if
// the DB has them, so an un-migrated DB still serves the base roster.
$cols = [];
foreach ($db->query('PRAGMA table_info(characters)') as $ci) {
    $cols[$ci['name']] = true;
}
$hasWaveDials = isset($cols['hp_base'], $cols['wave_min'], $cols['wave_max'], $cols['hp_cap'], $cols['hp_bands']);

$select = 'id, name, age, gender, type, description, avatar_path, pose_path';
if ($hasWaveDials) {
    $select .= ', hp_base, wave_min, wave_max, hp_cap, hp_bands';
}
$sql = "SELECT {$select} FROM characters";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY type, sort_order, name';

$stmt = $db->prepare($sql);
$stmt->execute($params);

/** Absolute URL for a stored image path (game fetches it directly), or null. */
$imgUrl = static function ($p): ?string {
    return grave_asset_abs_url($p === null ? null : (string) $p);
};

// Talk-bubble lines (migration 015), inline per character so the game's one
// startup fetch carries them: ENABLED lines only, keyed by character_id.
// Bodies may contain {tokens} (e.g. {password}) that the GAME resolves at
// display time — served verbatim here.
$messagesByChar = [];
try {
    $hasMsgs = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='character_messages'")->fetchColumn();
    if ($hasMsgs !== false) {
        $msgStmt = $db->query(
            'SELECT id, character_id, body, weight FROM character_messages
             WHERE enabled = 1 ORDER BY character_id, id');
        foreach ($msgStmt->fetchAll() as $m) {
            $messagesByChar[(int) $m['character_id']][] = [
                'id'     => (int) $m['id'],
                'body'   => (string) $m['body'],
                'weight' => max(1, (int) $m['weight']),
            ];
        }
    }
} catch (Throwable $e) {
    // No messages table / read error -> every character just gets [].
}

$out = [];
foreach ($stmt->fetchAll() as $c) {
    $row = [
        'id'          => (int) $c['id'],
        'name'        => (string) $c['name'],
        'age'         => ($c['age'] === null) ? null : (int) $c['age'],
        'gender'      => (string) ($c['gender'] ?? 'unknown'),
        'type'        => (string) $c['type'],
        'description' => $c['description'],
        'avatar'      => $imgUrl($c['avatar_path']),
        'pose'        => $imgUrl($c['pose_path']),
        'messages'    => $messagesByChar[(int) $c['id']] ?? [],
    ];

    // Wave-scaling dials — emitted flat, ONLY the keys that are set, so a
    // character with no dials is byte-identical to before (game applies its own
    // fallbacks). hp_bands is served as a parsed array. See the game repo:
    // docs/ROADMAP/wave-scaling-handoff.md.
    if ($hasWaveDials) {
        if ($c['hp_base']  !== null) { $row['hp_base']  = (int) $c['hp_base']; }
        if ($c['wave_min'] !== null) { $row['wave_min'] = (int) $c['wave_min']; }
        if ($c['wave_max'] !== null) { $row['wave_max'] = (int) $c['wave_max']; }
        if ($c['hp_cap']   !== null) { $row['hp_cap']   = (int) $c['hp_cap']; }
        if (!empty($c['hp_bands'])) {
            $decoded = json_decode((string) $c['hp_bands'], true);
            if (is_array($decoded) && $decoded) {
                $row['hp_bands'] = $decoded;
            }
        }
    }

    $out[] = $row;
}

grave_json_response(200, ['success' => true, 'count' => count($out), 'characters' => $out]);

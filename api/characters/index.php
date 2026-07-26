<?php
/**
 * THE DEAD LAST — API: character roster feed (runtime).
 *
 * GET /api/characters                      (public, read-only)
 *   -> { "success": true, "count": N, "characters": [ { id, name, age, gender,
 *        type, description, avatar, pose }, ... ] }
 *   The ACTIVE characters authored in Keeper (Characters tab). This is the
 *   canonical list piped into the game's runtime startup — the characters
 *   running in the world. Inactive characters are excluded. Ordered by
 *   type, then the admin's sort order, then name.
 *
 *   Optional: ?type=Human|NPC|Zombie|Enemy filters to one type.
 *   Optional: ?all=1 includes inactive rows too (for authoring/preview).
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

$sql = 'SELECT id, name, age, gender, type, description, avatar_path, pose_path
        FROM characters';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY type, sort_order, name';

$stmt = $db->prepare($sql);
$stmt->execute($params);

/** Absolute web path for a stored image path, or null. */
$imgUrl = static function ($p): ?string {
    $p = (string) ($p ?? '');
    return $p === '' ? null : '/' . ltrim($p, '/');
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
    $out[] = [
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
}

grave_json_response(200, ['success' => true, 'count' => count($out), 'characters' => $out]);

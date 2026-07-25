<?php
/**
 * Reaction toggle endpoint (2026-07-25, "reactions don't persist").
 *
 * POST { csrf_token, post_id, emoji } — toggles the logged-in user's reaction
 * on a post (INSERT if absent, DELETE if present; the reactions table's
 * UNIQUE(post_id, user_id, emoji) is the source of truth).
 *
 * Returns JSON the client rebuilds the chip row from:
 *   { ok: true, counts: { "👍": 3, ... }, mine: ["👍", ...] }
 */
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$me = auth_current_user();
if ($me === null) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Log in to react.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Bad request.']);
    exit;
}

// Same set the picker offers — anything else is rejected.
$allowed = ['👍', '❤️', '😂', '😮', '😢', '🔥'];
$emoji = (string) ($_POST['emoji'] ?? '');
$postId = (int) ($_POST['post_id'] ?? 0);

if ($postId <= 0 || !in_array($emoji, $allowed, true)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid reaction.']);
    exit;
}

$db = forum_db();

// Post must exist (no reacting into the void).
$chk = $db->prepare('SELECT id FROM posts WHERE id = ?');
$chk->execute([$postId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Post not found.']);
    exit;
}

$uid = (int) $me['id'];

// Toggle.
$sel = $db->prepare('SELECT id FROM reactions WHERE post_id = ? AND user_id = ? AND emoji = ?');
$sel->execute([$postId, $uid, $emoji]);
$existing = $sel->fetch();

if ($existing) {
    $db->prepare('DELETE FROM reactions WHERE id = ?')->execute([(int) $existing['id']]);
} else {
    $db->prepare('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, datetime("now"))')
        ->execute([$postId, $uid, $emoji]);
}

// Fresh truth for the client.
$counts = [];
$rows = $db->prepare('SELECT emoji, COUNT(*) AS n FROM reactions WHERE post_id = ? GROUP BY emoji');
$rows->execute([$postId]);
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $counts[$r['emoji']] = (int) $r['n'];
}

$mine = [];
$mrows = $db->prepare('SELECT emoji FROM reactions WHERE post_id = ? AND user_id = ?');
$mrows->execute([$postId, $uid]);
foreach ($mrows->fetchAll(PDO::FETCH_COLUMN) as $e) {
    $mine[] = $e;
}

echo json_encode(['ok' => true, 'counts' => $counts, 'mine' => $mine]);

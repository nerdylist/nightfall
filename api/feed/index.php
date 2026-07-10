<?php
/**
 * THE DEAD LAST — API: forum "latest posts" feed (public, read-only).
 *
 * GET /api/feed?section=<key>[&limit=<n>]
 *   -> { "section": "latest-news", "label": "Latest News",
 *        "category_id": 2, "category_url": "/bbs/category/2",
 *        "items": [ { id, title, excerpt, date, replies, views, url }, ... ] }
 *
 * Sections are configured in Keeper > Settings and stored as JSON in the
 * forum DB's `settings` table under the `feed_sections` key: an array of
 * { key, label, category_id, limit }. Unknown section -> 404 JSON error.
 *
 * Opens its own PDO to bbs/forum.db (same pattern as keeper_forum_db) —
 * api/ never includes bbs/ code. Never exposes fields beyond those above.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

/**
 * Direct read-only PDO connection to the forum's SQLite database.
 */
function feed_forum_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . __DIR__ . '/../../bbs/forum.db', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec('PRAGMA journal_mode = WAL;');

    return $pdo;
}

$sectionKey = isset($_GET['section']) ? trim((string) $_GET['section']) : '';

if ($sectionKey === '') {
    grave_json_response(400, ['success' => false, 'error' => 'Missing required "section" parameter.']);
}

try {
    $db = feed_forum_db();

    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['feed_sections']);
    $raw = $stmt->fetchColumn();
} catch (PDOException $e) {
    grave_json_response(404, ['success' => false, 'error' => 'Unknown feed section.']);
}

$sections = ($raw !== false) ? json_decode((string) $raw, true) : null;
if (!is_array($sections)) {
    $sections = [];
}

$section = null;
foreach ($sections as $candidate) {
    if (is_array($candidate) && ($candidate['key'] ?? null) === $sectionKey) {
        $section = $candidate;
        break;
    }
}

if ($section === null) {
    grave_json_response(404, ['success' => false, 'error' => 'Unknown feed section.']);
}

$categoryId = (int) ($section['category_id'] ?? 0);
$limit = (int) ($section['limit'] ?? 3);

if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
    $limit = (int) $_GET['limit'];
}
$limit = max(1, min(20, $limit));

$stmt = $db->prepare(
    'SELECT id, title, excerpt, created_at, replies, views
     FROM threads
     WHERE category_id = ?
     ORDER BY created_at DESC
     LIMIT ?'
);
$stmt->bindValue(1, $categoryId, PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->execute();

$items = [];
foreach ($stmt->fetchAll() as $row) {
    $items[] = [
        'id'      => (int) $row['id'],
        'title'   => (string) $row['title'],
        'excerpt' => (string) ($row['excerpt'] ?? ''),
        'date'    => (string) ($row['created_at'] ?? ''),
        'replies' => (int) $row['replies'],
        'views'   => (int) $row['views'],
        'url'     => '/bbs/thread/' . (int) $row['id'],
    ];
}

grave_json_response(200, [
    'section'      => $sectionKey,
    'label'        => (string) ($section['label'] ?? $sectionKey),
    'category_id'  => $categoryId,
    'category_url' => '/bbs/category/' . $categoryId,
    'items'        => $items,
]);

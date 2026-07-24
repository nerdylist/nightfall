<?php
/**
 * THE DEAD LAST — Forum data reset (production / CloudWays).
 *
 * The forum was seeded from mock data (bbs/data/mock.php), which baked FAKE
 * activity into the categories table via the denormalized `thread_count` /
 * `post_count` columns (e.g. "General Chat — 312 threads / 8,471 posts") and
 * inserted mock threads / posts / chat / reactions. Deleting site users never
 * touched any of this — it lives entirely in bbs/forum.db.
 *
 * This one-off, token-guarded, web-runnable script:
 *   1. Backs up bbs/forum.db before any writes.
 *   2. Deletes ALL rows from threads, posts, chat_messages, reactions
 *      (the mock content). Categories are KEPT.
 *   3. Recomputes every category's thread_count / post_count / last_activity
 *      from the now-real (empty) tables — so the numbers become honest 0s.
 *
 * CloudWays has no sqlite3 CLI, so this is triggered by visiting a URL:
 *
 *     https://<yourdomain>/bbs/reset-forum.php?token=YOURTOKEN
 *
 * REQUIRES: MIGRATE_TOKEN=<something-secret> in bbs/.env. Without it, this
 * script refuses to run (fails closed).
 *
 * SECURITY: after you've reset production, DELETE this file (or unset
 * MIGRATE_TOKEN) so it can never be triggered again.
 */

require __DIR__ . '/config.php';   // exposes $GLOBALS['CONFIG'] + $forum_env
require_once __DIR__ . '/db.php';  // exposes forum_db()

header('Content-Type: text/plain; charset=utf-8');

// ---------------------------------------------------------------------------
// 1) Token guard — fail closed.
// ---------------------------------------------------------------------------
$expectedToken = isset($forum_env['MIGRATE_TOKEN']) ? (string) $forum_env['MIGRATE_TOKEN'] : '';

if ($expectedToken === '') {
    echo "Refusing to run: MIGRATE_TOKEN is not set.\n";
    echo "Add a line to bbs/.env, e.g.  MIGRATE_TOKEN=<something-secret>\n";
    echo "then visit  /bbs/reset-forum.php?token=<something-secret>\n";
    exit(1);
}

$providedToken = (string) ($_GET['token'] ?? '');

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo "Forbidden — valid ?token= required\n";
    exit;
}

echo "THE DEAD LAST — forum data reset\n";

// ---------------------------------------------------------------------------
// 2) Resolve DB path + back up before any writes.
// ---------------------------------------------------------------------------
$dbFile = (string) ($GLOBALS['CONFIG']['DB_FILE'] ?? 'forum.db');
$dbPath = __DIR__ . '/' . $dbFile;
echo "DB path: {$dbPath}\n";

if (!is_file($dbPath)) {
    echo "ERROR: forum DB not found at {$dbPath} — nothing to reset.\n";
    exit(1);
}

$backupPath = $dbPath . '.bak-reset-' . date('Ymd-His');
if (!copy($dbPath, $backupPath)) {
    echo "ERROR: could not create backup at {$backupPath} — aborting before any writes.\n";
    exit(1);
}
echo "Backup: {$backupPath}\n";

$pdo = forum_db();

// ---------------------------------------------------------------------------
// 3) Snapshot BEFORE.
// ---------------------------------------------------------------------------
$before = [
    'categories'    => (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'threads'       => (int) $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
    'posts'         => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'chat_messages' => (int) $pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn(),
    'reactions'     => (int) $pdo->query('SELECT COUNT(*) FROM reactions')->fetchColumn(),
];
echo "\nBEFORE:\n";
foreach ($before as $t => $n) {
    echo "  {$t}: {$n}\n";
}

// ---------------------------------------------------------------------------
// 4) Delete mock content + recompute category counters, in one transaction.
// ---------------------------------------------------------------------------
$pdo->beginTransaction();
try {
    // Order respects FKs: reactions -> posts/chat -> threads.
    $pdo->exec('DELETE FROM reactions');
    $pdo->exec('DELETE FROM chat_messages');
    $pdo->exec('DELETE FROM posts');
    $pdo->exec('DELETE FROM threads');

    // Recompute denormalized counters per category from the now-empty tables.
    // (Written generically so it stays correct if real threads/posts exist.)
    $pdo->exec(
        'UPDATE categories SET
            thread_count = (SELECT COUNT(*) FROM threads t WHERE t.category_id = categories.id),
            post_count   = (SELECT COUNT(*) FROM posts p
                            JOIN threads t ON t.id = p.thread_id
                            WHERE t.category_id = categories.id),
            last_activity = NULL'
    );

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\nERROR during reset — rolled back. No changes applied.\n";
    echo $e->getMessage() . "\n";
    exit(1);
}

// ---------------------------------------------------------------------------
// 5) Snapshot AFTER.
// ---------------------------------------------------------------------------
echo "\nAFTER:\n";
$cats = $pdo->query('SELECT id, name, thread_count, post_count FROM categories ORDER BY sort_order, id');
foreach ($cats as $c) {
    printf("  [%d] %s — %d threads / %d posts\n", $c['id'], $c['name'], $c['thread_count'], $c['post_count']);
}
$after = [
    'threads'       => (int) $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
    'posts'         => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'chat_messages' => (int) $pdo->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn(),
    'reactions'     => (int) $pdo->query('SELECT COUNT(*) FROM reactions')->fetchColumn(),
];
echo "\n  content rows remaining: "
    . "threads={$after['threads']} posts={$after['posts']} "
    . "chat={$after['chat_messages']} reactions={$after['reactions']}\n";

echo "\nDone. Reload the forum — the counts now reflect reality.\n";
echo "SECURITY: delete bbs/reset-forum.php now that the reset is complete.\n";

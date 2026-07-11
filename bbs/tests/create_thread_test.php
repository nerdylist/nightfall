<?php
/**
 * Test for create_thread() in data/db.php.
 *
 * Verifies a thread row + first post row are created atomically with the right
 * values, that denormalized counters are bumped, and that the returned id is
 * correct. Cleans up everything it inserts so the DB is left untouched.
 *
 * Run: php tests/create_thread_test.php
 */

require __DIR__ . '/../config.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../data/db.php';

$db = forum_db();

$failures = 0;
function check($label, $cond)
{
    global $failures;
    if ($cond) {
        echo "  PASS: {$label}\n";
    } else {
        echo "  FAIL: {$label}\n";
        $failures++;
    }
}

// --- Find valid existing ids -------------------------------------------------
$categoryId = (int) $db->query('SELECT id FROM categories ORDER BY id ASC LIMIT 1')->fetchColumn();
$authorId   = (int) $db->query("SELECT id FROM host.users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($authorId === 0) {
    $authorId = (int) $db->query('SELECT id FROM host.users ORDER BY id ASC LIMIT 1')->fetchColumn();
}

echo "Using category_id={$categoryId}, author_id={$authorId}\n";

// --- Snapshot counters before ------------------------------------------------
$startedBefore = (int) $db->query("SELECT threads_started FROM host.users WHERE id = {$authorId}")->fetchColumn();
$tcBefore      = (int) $db->query("SELECT thread_count FROM categories WHERE id = {$categoryId}")->fetchColumn();
$pcBefore      = (int) $db->query("SELECT post_count FROM categories WHERE id = {$categoryId}")->fetchColumn();

$title   = 'TEST THREAD ' . uniqid();
$body    = '  This is the original post body for the create_thread test.  ';
$excerpt = 'This is the original post body...';

// --- Exercise ----------------------------------------------------------------
$threadId = create_thread($categoryId, $authorId, $title, $body, $excerpt);
echo "create_thread() returned id={$threadId}\n";

check('returned id is a positive int', is_int($threadId) && $threadId > 0);

// --- Assert thread row -------------------------------------------------------
$tStmt = $db->prepare('SELECT * FROM threads WHERE id = ?');
$tStmt->execute([$threadId]);
$thread = $tStmt->fetch(PDO::FETCH_ASSOC);

check('thread row exists', $thread !== false);
check('thread.category_id matches', (int) $thread['category_id'] === $categoryId);
check('thread.author_id matches', (int) $thread['author_id'] === $authorId);
check('thread.title matches (trimmed)', $thread['title'] === $title);
check('thread.excerpt matches', $thread['excerpt'] === $excerpt);
check('thread.replies = 0', (int) $thread['replies'] === 0);
check('thread.views = 0', (int) $thread['views'] === 0);
check('thread.pinned = 0', (int) $thread['pinned'] === 0);
check('thread.locked = 0', (int) $thread['locked'] === 0);
check('thread.hot = 0', (int) $thread['hot'] === 0);
check('thread.created_at is ISO 8601', (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', (string) $thread['created_at']));
check('thread.updated_at == created_at', $thread['updated_at'] === $thread['created_at']);
check('thread.last_activity is display string', $thread['last_activity'] === 'just now');

// --- Assert post row ---------------------------------------------------------
$pStmt = $db->prepare('SELECT * FROM posts WHERE thread_id = ?');
$pStmt->execute([$threadId]);
$posts = $pStmt->fetchAll(PDO::FETCH_ASSOC);

check('exactly one post created', count($posts) === 1);
$post = $posts[0] ?? [];
check('post.author_id matches', (int) ($post['author_id'] ?? -1) === $authorId);
check('post.body is trimmed', ($post['body'] ?? null) === trim($body));
check('post.created is display string', ($post['created'] ?? null) === 'just now');
check('post.created_at is ISO 8601', (bool) preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/', (string) ($post['created_at'] ?? '')));

// --- Assert counters bumped --------------------------------------------------
$startedAfter = (int) $db->query("SELECT threads_started FROM host.users WHERE id = {$authorId}")->fetchColumn();
$tcAfter      = (int) $db->query("SELECT thread_count FROM categories WHERE id = {$categoryId}")->fetchColumn();
$pcAfter      = (int) $db->query("SELECT post_count FROM categories WHERE id = {$categoryId}")->fetchColumn();

check('users.threads_started +1', $startedAfter === $startedBefore + 1);
check('categories.thread_count +1', $tcAfter === $tcBefore + 1);
check('categories.post_count +1', $pcAfter === $pcBefore + 1);

// --- Assert validation throws ------------------------------------------------
$threw = false;
try {
    create_thread($categoryId, $authorId, '   ', 'body', 'x');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
check('empty title throws InvalidArgumentException', $threw);

$threw = false;
try {
    create_thread($categoryId, $authorId, 'title', '   ', 'x');
} catch (InvalidArgumentException $e) {
    $threw = true;
}
check('empty body throws InvalidArgumentException', $threw);

// --- Cleanup -----------------------------------------------------------------
$db->beginTransaction();
try {
    $db->prepare('DELETE FROM posts WHERE thread_id = ?')->execute([$threadId]);
    $db->prepare('DELETE FROM threads WHERE id = ?')->execute([$threadId]);
    // Restore counters to their pre-test values.
    $db->prepare('UPDATE host.users SET threads_started = ? WHERE id = ?')->execute([$startedBefore, $authorId]);
    $db->prepare('UPDATE categories SET thread_count = ?, post_count = ? WHERE id = ?')
       ->execute([$tcBefore, $pcBefore, $categoryId]);
    $db->commit();
    echo "Cleanup: removed test thread/post and restored counters.\n";
} catch (Throwable $e) {
    $db->rollBack();
    echo "Cleanup FAILED: " . $e->getMessage() . "\n";
    $failures++;
}

echo $failures === 0 ? "\nALL TESTS PASSED\n" : "\n{$failures} TEST(S) FAILED\n";
exit($failures === 0 ? 0 : 1);

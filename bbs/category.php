<?php
require __DIR__ . '/config.php';              // exposes $CONFIG
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/partials/category-badge.php';
auth_start_session();
$data = require __DIR__ . '/data/live.php';   // returns mock array -> $data
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;

// friendly-URL: /bbs/category/:id exposes id via $_ROUTE_PARAMS; bridge to $_GET
if (isset($GLOBALS['_ROUTE_PARAMS']['id']) && !isset($_GET['id'])) { $_GET['id'] = $GLOBALS['_ROUTE_PARAMS']['id']; }

// Relative time for the sidebar's LAST ACTIVITY ("just now" / "5m ago" /
// "3h ago" / "2d ago" / date). Single consumer; promote to a lib if reused.
if (!function_exists('forum_time_ago')) {
    function forum_time_ago(string $ts): string
    {
        $t = strtotime($ts);
        if ($t === false) { return $ts; }
        $d = time() - $t;
        if ($d < 60) { return 'just now'; }
        if ($d < 3600) { return floor($d / 60) . 'm ago'; }
        if ($d < 86400) { return floor($d / 3600) . 'h ago'; }
        if ($d < 604800) { return floor($d / 86400) . 'd ago'; }
        return date('M j, Y', $t);
    }
}

// Resolve requested category id (default to first category).
$requestedId = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($data['categories'][0]['id'] ?? 0);

$category = null;
foreach ($data['categories'] as $cat) {
    if ((int) $cat['id'] === $requestedId) {
        $category = $cat;
        break;
    }
}
if ($category === null) {
    $category = $data['categories'][0] ?? null;
}
$categoryId = $category !== null ? (int) $category['id'] : 0;

// Gather threads for this category — ONLY this category. (The old "fall back
// to all threads if none match" scaffold made every EMPTY category list the
// entire forum — Boss 2026-07-25: "my post shows up in every category".
// An empty category now renders an honest empty state instead.)
$categoryThreads = [];
foreach ($data['threads'] as $t) {
    if ((int) $t['category_id'] === $categoryId) {
        $categoryThreads[] = $t;
    }
}

include __DIR__ . '/partials/head.php';       // DOCTYPE..head..</head><body>
include __DIR__ . '/partials/header.php';     // <header class="site-header">
?>
<main class="container">
  <div class="category-layout">
    <section class="thread-list" aria-label="Threads">
      <?php if (empty($categoryThreads)): ?>
        <p class="thread-list-empty">There are no current threads in this category. <a href="<?= htmlspecialchars(($BASE ?? '/bbs/') . 'write.php?category=' . (int) $categoryId) ?>">Click here</a> to create one!</p>
      <?php else: ?>
        <?php foreach ($categoryThreads as $thread): ?>
          <?php include __DIR__ . '/partials/thread-row.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <aside class="category-aside">
      <?php if (auth_is_logged_in()): ?>
      <a class="btn btn-primary new-thread-btn" href="/bbs/write.php?category=<?= (int)$categoryId ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        New Thread
      </a>
      <?php endif; ?>

      <div class="cat-info"<?php if ($category !== null): ?> style="--cat-color: <?= forum_category_color($category) ?>;"<?php endif; ?>>
        <div class="cat-info-head">
          <?php if ($category !== null): ?><span class="cat-badge<?= forum_category_badge_is_image($category) ? ' is-image' : '' ?>"><?= forum_category_badge($category) ?></span><?php endif; ?>
          <?php $catInfoName = htmlspecialchars($category['name'] ?? 'Forum'); ?>
          <h1 class="cat-info-name forum-title-layered" data-title="<?= $catInfoName ?>"><?= $catInfoName ?></h1>
        </div>
        <p class="cat-info-desc"><?= htmlspecialchars($category['description'] ?? '') ?></p>
        <?php
        // LIVE STATS (Boss 2026-07-25: sidebar showed THREADS 2 / POSTS 2 on
        // an empty category). The categories table carries install-time
        // counter columns that nothing maintains — deletes/creates drifted
        // them immediately. Count the actual tables instead, every render:
        // THREADS = threads in this category, COMMENTS = live-chat messages
        // on those threads. Cheap at this scale; can denormalize later if
        // the forum ever gets big enough to care.
        require_once __DIR__ . '/db.php';
        $sdb = forum_db();
        $sq = $sdb->prepare('SELECT COUNT(*) FROM threads WHERE category_id = ?');
        $sq->execute([$categoryId]);
        $liveThreads = (int) $sq->fetchColumn();
        $cq = $sdb->prepare(
            'SELECT COUNT(*) FROM chat_messages WHERE thread_id IN
             (SELECT id FROM threads WHERE category_id = ?)'
        );
        $cq->execute([$categoryId]);
        $liveComments = (int) $cq->fetchColumn();
        $aq = $sdb->prepare(
            'SELECT MAX(t) FROM (
                SELECT MAX(created_at) AS t FROM threads WHERE category_id = :c
                UNION ALL
                SELECT MAX(created_at) AS t FROM chat_messages WHERE thread_id IN
                    (SELECT id FROM threads WHERE category_id = :c)
             )'
        );
        $aq->execute(['c' => $categoryId]);
        $liveActivity = (string) ($aq->fetchColumn() ?: '');
        ?>
        <dl class="cat-info-stats">
          <div class="cat-info-stat">
            <dt>Threads</dt>
            <dd><?= number_format($liveThreads) ?></dd>
          </div>
          <div class="cat-info-stat">
            <dt>Comments</dt>
            <dd><?= number_format($liveComments) ?></dd>
          </div>
          <div class="cat-info-stat">
            <dt>Last activity</dt>
            <dd><?= htmlspecialchars($liveActivity !== '' ? forum_time_ago($liveActivity) : '—') ?></dd>
          </div>
        </dl>
      </div>
    </aside>
  </div>
</main>
<?php
include __DIR__ . '/partials/footer.php';     // footer + scripts + </body></html>

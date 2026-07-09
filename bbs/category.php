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

// Gather threads for this category; fall back to all threads if none match.
$categoryThreads = [];
foreach ($data['threads'] as $t) {
    if ((int) $t['category_id'] === $categoryId) {
        $categoryThreads[] = $t;
    }
}
if (empty($categoryThreads)) {
    $categoryThreads = $data['threads'];
}

include __DIR__ . '/partials/head.php';       // DOCTYPE..head..</head><body>
include __DIR__ . '/partials/header.php';     // <header class="site-header">
?>
<main class="container">
  <div class="category-layout">
    <section class="thread-list" aria-label="Threads">
      <?php foreach ($categoryThreads as $thread): ?>
        <?php include __DIR__ . '/partials/thread-row.php'; ?>
      <?php endforeach; ?>
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
          <h1 class="cat-info-name"><?= htmlspecialchars($category['name'] ?? 'Forum') ?></h1>
        </div>
        <p class="cat-info-desc"><?= htmlspecialchars($category['description'] ?? '') ?></p>
        <dl class="cat-info-stats">
          <div class="cat-info-stat">
            <dt>Threads</dt>
            <dd><?= number_format((int)($category['thread_count'] ?? 0)) ?></dd>
          </div>
          <div class="cat-info-stat">
            <dt>Posts</dt>
            <dd><?= number_format((int)($category['post_count'] ?? 0)) ?></dd>
          </div>
          <div class="cat-info-stat">
            <dt>Last activity</dt>
            <dd><?= htmlspecialchars($category['last_activity'] ?? '') ?></dd>
          </div>
        </dl>
      </div>
    </aside>
  </div>
</main>
<?php
include __DIR__ . '/partials/footer.php';     // footer + scripts + </body></html>

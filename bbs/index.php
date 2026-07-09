<?php
require __DIR__ . '/config.php';              // exposes $CONFIG
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
$data = require __DIR__ . '/data/live.php';   // returns mock array -> $data
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;
include __DIR__ . '/partials/head.php';       // DOCTYPE..head..</head><body>
include __DIR__ . '/partials/header.php';     // <header class="site-header">

// Aggregate forum stats for the hero.
$totalThreads = 0;
$totalPosts = 0;
foreach ($data['categories'] as $cat) {
    $totalThreads += (int)($cat['thread_count'] ?? 0);
    $totalPosts += (int)($cat['post_count'] ?? 0);
}
$totalMembers = count($data['users'] ?? []);
?>
<main class="container">
  <section class="hero">
    <span class="hero-eyebrow">Community</span>
    <h1 class="hero-title">Welcome to <?= htmlspecialchars($CONFIG['SITE_NAME']) ?></h1>
    <p class="hero-lede">A calm, ultra-dark place to talk shop, share what you are building, and get unstuck. Jump into a category below and join the conversation.</p>
    <dl class="hero-stats">
      <div class="hero-stat">
        <dd class="hero-stat-num"><?= number_format($totalThreads) ?></dd>
        <dt class="hero-stat-label">Threads</dt>
      </div>
      <div class="hero-stat">
        <dd class="hero-stat-num"><?= number_format($totalPosts) ?></dd>
        <dt class="hero-stat-label">Posts</dt>
      </div>
      <div class="hero-stat">
        <dd class="hero-stat-num"><?= number_format($totalMembers) ?></dd>
        <dt class="hero-stat-label">Members</dt>
      </div>
    </dl>
  </section>

  <?php
  $featured = array_filter($data['categories'], fn($c) => !empty($c['featured']));
  if (empty($featured)) { $featured = $data['categories']; }
  ?>
  <div class="section-head">
    <h2 class="section-title">Categories</h2>
    <a class="section-hint section-link" href="/bbs/forums">View all <?= count($data['categories']) ?> forums &rarr;</a>
  </div>

  <section class="category-grid">
    <?php foreach ($featured as $category): ?>
      <?php include __DIR__ . '/partials/category-card.php'; ?>
    <?php endforeach; ?>
  </section>
</main>
<?php
include __DIR__ . '/partials/footer.php';     // footer + scripts + </body></html>

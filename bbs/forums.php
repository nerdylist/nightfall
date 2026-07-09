<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
$data = require __DIR__ . '/data/live.php';
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;
require_once __DIR__ . '/partials/category-badge.php';
include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main class="container">
  <div class="section-head">
    <h2 class="section-title">All Forums</h2>
    <span class="section-hint"><?= count($data['categories']) ?> forums</span>
  </div>

  <section class="forum-rows">
    <?php foreach ($data['categories'] as $category): ?>
      <a class="forum-row" href="category.php?id=<?= (int)$category['id'] ?>" style="--cat-color: <?= forum_category_color($category) ?>;">
        <span class="forum-row-badge cat-badge<?= forum_category_badge_is_image($category) ? ' is-image' : '' ?>"><?= forum_category_badge($category) ?></span>
        <span class="forum-row-main">
          <span class="forum-row-name"><?= htmlspecialchars($category['name'] ?? '') ?></span>
          <span class="forum-row-desc"><?= htmlspecialchars($category['description'] ?? '') ?></span>
        </span>
        <span class="forum-row-stats">
          <span class="forum-row-stat"><span class="forum-row-num"><?= number_format((int)($category['thread_count'] ?? 0)) ?></span> threads</span>
          <span class="forum-row-stat"><span class="forum-row-num"><?= number_format((int)($category['post_count'] ?? 0)) ?></span> posts</span>
          <span class="forum-row-time"><?= htmlspecialchars($category['last_activity'] ?? '') ?></span>
        </span>
      </a>
    <?php endforeach; ?>
  </section>
</main>
<?php
include __DIR__ . '/partials/footer.php';

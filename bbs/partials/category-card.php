<?php
$data = $data ?? require __DIR__ . '/../data/mock.php';

if (empty($category)) {
    return;
}

require_once __DIR__ . '/category-badge.php';
?>
<a class="category-card" href="category.php?id=<?= (int)$category['id'] ?>" style="--cat-color: <?= forum_category_color($category) ?>;">
  <span class="card-head">
    <span class="icon<?= forum_category_badge_is_image($category) ? ' is-image' : '' ?>"><?= forum_category_badge($category) ?></span>
    <span class="name"><?= htmlspecialchars($category['name'] ?? '') ?></span>
  </span>
  <span class="desc"><?= htmlspecialchars($category['description'] ?? '') ?></span>
  <span class="meta">
    <span class="meta-stat"><span class="meta-num"><?= number_format((int)($category['thread_count'] ?? 0)) ?></span> threads</span>
    <span class="meta-stat"><span class="meta-num"><?= number_format((int)($category['post_count'] ?? 0)) ?></span> posts</span>
    <span class="meta-time"><?= htmlspecialchars($category['last_activity'] ?? '') ?></span>
  </span>
</a>

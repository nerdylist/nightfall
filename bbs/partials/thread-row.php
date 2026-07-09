<?php
$data = $data ?? require __DIR__ . '/../data/mock.php';
require_once __DIR__ . '/avatar.php';

if (empty($thread)) {
    return;
}

// Find author.
$author = ['display_name' => 'Unknown'];
foreach ($data['users'] as $u) {
    if ($u['id'] === $thread['author_id']) {
        $author = $u;
        break;
    }
}
?>
<div class="thread-row">
  <div class="thread-avatar">
    <?php render_avatar($author['display_name'], 40); ?>
  </div>
  <div class="thread-main">
    <div class="thread-head">
      <a class="title" href="thread.php?id=<?= (int)$thread['id'] ?>"><?= htmlspecialchars($thread['title']) ?></a>
      <?php if (!empty($thread['pinned'])): ?><span class="badge badge-pinned">Pinned</span><?php endif; ?>
      <?php if (!empty($thread['hot'])): ?><span class="badge badge-hot">Hot</span><?php endif; ?>
    </div>
    <p class="thread-excerpt"><?= htmlspecialchars($thread['excerpt']) ?></p>
    <div class="thread-sub">
      <span class="thread-author"><?= htmlspecialchars($author['display_name']) ?></span>
      <span class="dot" aria-hidden="true">&middot;</span>
      <span><?= htmlspecialchars($thread['last_activity']) ?></span>
    </div>
  </div>
  <div class="thread-meta">
    <span class="thread-stat" title="<?= (int)$thread['replies'] ?> replies">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 11.5a8.38 8.38 0 0 1-8.5 8.5 8.5 8.5 0 0 1-3.8-.9L3 21l1.9-5.7A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 12.5 3 8.38 8.38 0 0 1 21 11.5z"></path>
      </svg>
      <span class="num"><?= number_format((int)$thread['replies']) ?></span>
    </span>
    <span class="thread-stat" title="<?= (int)$thread['views'] ?> views">
      <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
      <span class="num"><?= number_format((int)$thread['views']) ?></span>
    </span>
  </div>
</div>

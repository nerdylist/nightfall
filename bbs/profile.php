<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
$data = require __DIR__ . '/data/live.php';
$me = auth_current_user();
$data['current_user'] = $me ? (int)$me['id'] : 0;
require_once __DIR__ . '/partials/avatar.php';

// Resolve requested user id (default to current user).
$requestedId = isset($_GET['user']) ? (int) $_GET['user'] : (int) $data['current_user'];

$profileUser = null;
foreach ($data['users'] as $u) {
    if ((int) $u['id'] === $requestedId) {
        $profileUser = $u;
        break;
    }
}
if ($profileUser === null) {
    // Fall back to the current user, then the first user.
    foreach ($data['users'] as $u) {
        if ((int) $u['id'] === (int) $data['current_user']) {
            $profileUser = $u;
            break;
        }
    }
    if ($profileUser === null && !empty($data['users'])) {
        $profileUser = $data['users'][0];
    }
}

// Collect this user's threads; fall back to a few recent threads if none.
$userThreads = [];
foreach ($data['threads'] as $t) {
    if ((int) $t['author_id'] === (int) $profileUser['id']) {
        $userThreads[] = $t;
    }
}
if (empty($userThreads)) {
    $userThreads = array_slice($data['threads'], 0, 3);
}

// Format join date for display.
$joinDisplay = $profileUser['join_date'];
$ts = strtotime((string) $profileUser['join_date']);
if ($ts !== false) {
    $joinDisplay = date('F j, Y', $ts);
}

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main class="container py-7">
  <div class="profile-banner"></div>

  <div class="profile-head">
    <div class="profile-avatar"><?php render_avatar($profileUser['display_name'], 120); ?></div>
    <div class="profile-head-info">
      <div class="profile-name"><?= htmlspecialchars($profileUser['display_name']) ?></div>
      <div class="profile-meta">@<?= htmlspecialchars($profileUser['username']) ?> &middot; Joined <?= htmlspecialchars($joinDisplay) ?></div>
    </div>
  </div>

  <?php if (!empty($profileUser['bio'])): ?>
    <p class="profile-bio"><?= htmlspecialchars($profileUser['bio']) ?></p>
  <?php endif; ?>

  <div class="profile-stats">
    <div class="stat">
      <span class="num"><?= (int) ($profileUser['threads_started'] ?? 0) ?></span>
      <span class="label">Threads Started</span>
    </div>
    <div class="stat">
      <span class="num"><?= (int) ($profileUser['chat_messages'] ?? 0) ?></span>
      <span class="label">Chat Messages</span>
    </div>
    <div class="stat">
      <span class="num"><?= (int) ($profileUser['reputation'] ?? 0) ?></span>
      <span class="label">Reputation</span>
    </div>
  </div>

  <h3 class="mt-6 mb-4">Recent activity</h3>
  <div class="activity-list">
    <?php foreach ($userThreads as $thread): ?>
      <?php include __DIR__ . '/partials/thread-row.php'; ?>
    <?php endforeach; ?>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

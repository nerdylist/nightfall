<?php
if (!isset($CONFIG)) { require __DIR__ . '/../config.php'; }
$BASE = $BASE ?? '';
require_once __DIR__ . '/../lib/auth.php';
auth_start_session();
$currentUser = auth_current_user();

// Current forum URI, for round-tripping back here after SSO login.
$navNext = $_SERVER['REQUEST_URI'] ?? '/bbs/';
?>
<nav class="site-nav">
  <div class="container site-nav__inner">
    <a href="/index.php" class="site-nav__brand">THE DEAD LAST</a>
    <div class="site-nav__links">
      <a href="#" class="site-nav__link">Game</a>
      <a href="#" class="site-nav__link">News</a>
      <a href="/bbs/" class="site-nav__link">Community</a>
      <a href="#" class="site-nav__link">Support</a>
      <?php if ($currentUser !== null && auth_is_admin()): ?>
        <a href="<?= $BASE ?>admin/" class="site-nav__link">Admin</a>
      <?php endif; ?>
    </div>
    <div class="site-nav__search">
      <button type="button" class="site-nav__search-trigger" id="nav-search-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="nav-search-form" aria-label="Search" title="Search">
        <svg class="site-nav__search-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
          <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>
        </svg>
      </button>
      <form class="site-nav__search-form" id="nav-search-form">
        <input class="site-nav__search-input" id="nav-search-input" type="search" aria-label="Search" placeholder="Search <?= htmlspecialchars($CONFIG['SITE_NAME']) ?>...">
      </form>
    </div>
    <div class="site-nav__auth">
      <?php if ($currentUser !== null): ?>
        <a href="<?= $BASE ?>profile.php?user=<?= urlencode($currentUser['username']) ?>" class="site-nav__link site-nav__username"><?= htmlspecialchars(strtoupper($currentUser['username'])) ?></a>
        <a href="/logout.php" class="btn btn-ghost site-nav__cta">Logout</a>
      <?php else: ?>
        <a href="/bbs/login.php?next=<?= urlencode($navNext) ?>" class="btn btn-ghost site-nav__cta">Login</a>
        <a href="/bbs/register.php" class="btn btn-primary site-nav__cta">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php
if (!isset($CONFIG)) { require __DIR__ . '/../config.php'; }
$BASE = $BASE ?? '';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/avatar.php';
auth_start_session();
$currentUser = auth_current_user();

// Resolve the current page to highlight the matching nav link.
$navPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navForums = in_array($navPage, ['forums.php', 'category.php', 'thread.php'], true);
$navHome = ($navPage === 'index.php');
?>
<header class="site-header">
  <div class="container">
    <a class="logo" href="<?= $BASE ?>index.php"><span class="logo-text"><?= htmlspecialchars($CONFIG['SITE_NAME']) ?></span></a>
    <nav class="nav-links">
      <a class="<?= $navHome ? 'active' : '' ?>" href="<?= $BASE ?>index.php">Home</a>
      <a class="<?= $navForums ? 'active' : '' ?>" href="<?= $BASE ?>forums.php">Forums</a>
    </nav>
    <input class="nav-search" type="search" aria-label="Search" placeholder="Search <?= htmlspecialchars($CONFIG['SITE_NAME']) ?>...">
    <div class="theme-switcher">
      <button class="theme-trigger" id="theme-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="theme-dropdown" aria-label="Change theme" title="Change theme">
        <span class="theme-icon" aria-hidden="true"></span>
      </button>
      <div class="theme-dropdown" id="theme-dropdown" role="menu" aria-label="Theme">
        <button class="theme-option" type="button" role="menuitemradio" data-theme="midnight">Midnight</button>
        <button class="theme-option" type="button" role="menuitemradio" data-theme="dusk">Dusk</button>
        <button class="theme-option" type="button" role="menuitemradio" data-theme="light">Light</button>
        <button class="theme-option" type="button" role="menuitemradio" data-theme="darkness">Darkness</button>
      </div>
    </div>
    <?php if ($currentUser !== null): ?>
    <div class="user-menu">
      <button class="user-menu-trigger" id="user-menu-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="user-dropdown"><?php render_avatar($currentUser['display_name'], 36); ?><span class="user-name"><?= htmlspecialchars($currentUser['display_name']) ?></span></button>
      <div class="user-dropdown" id="user-dropdown" role="menu">
        <a href="<?= $BASE ?>profile.php" role="menuitem">Profile</a>
        <a href="<?= $BASE ?>settings.php" role="menuitem">Settings</a>
        <?php if (auth_is_admin()): ?><a href="<?= $BASE ?>admin/" role="menuitem">Admin</a><?php endif; ?>
        <div class="divider"></div>
        <form method="post" action="/logout.php" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" role="menuitem">Log out</button>
        </form>
      </div>
    </div>
    <?php else: ?>
    <div class="auth-actions">
      <a class="btn btn-ghost" href="<?= $BASE ?>login.php">Log in</a>
      <a class="btn btn-primary" href="<?= $BASE ?>register.php">Register</a>
    </div>
    <?php endif; ?>
  </div>
</header>

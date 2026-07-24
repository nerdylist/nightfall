<?php
/**
 * Shared site header/nav partial — used by BOTH the host app (root) and the
 * forum (bbs/, via bbs/partials/header.php) under SSO (shared PHP session).
 *
 * Context flags (set by the including page/partial before requiring this
 * file; all optional, defaults match the host's own usage):
 *
 *   $NAV_ADMIN_URL         ?string When set, render an ADMIN link pointing
 *                                  here. Default null (no admin link).
 *   $NAV_LOGIN_URL         string  href for the Login CTA. Default '/login.php'.
 *   $NAV_REGISTER_URL      string  href for the Register CTA. Default '/register.php'.
 *   $NAV_SEARCH_PLACEHOLDER string Placeholder text for the search input.
 *                                  Default 'Search THE DEAD LAST...'.
 *
 * Auth state is resolved directly from the shared session + host DB (same
 * approach as the host's original nav.php) — both host and forum contexts
 * share one PHP session and $_SESSION['user_id'] always refers to the HOST
 * users.id, so this works correctly from either include site.
 */
require_once __DIR__ . '/../config.php';

// Default the Admin link to Keeper for admins when no caller set it. An
// explicit non-null caller value (e.g. the forum's '/bbs/admin/') is preserved
// by the isset() check; the only recomputed case is a caller's explicit null,
// which is a non-admin context and resolves back to null anyway.
if (!isset($NAV_ADMIN_URL)) {
    $NAV_ADMIN_URL = (function_exists('grave_is_admin') && grave_is_admin()) ? '/keeper/' : null;
}
$NAV_LOGIN_URL = $NAV_LOGIN_URL ?? '/login';
$NAV_REGISTER_URL = $NAV_REGISTER_URL ?? '/register';
$NAV_SEARCH_PLACEHOLDER = $NAV_SEARCH_PLACEHOLDER ?? 'Search THE DEAD LAST...';

// Nav auth state: resolve the logged-in user (if any) from the shared
// session. A user_id with no matching DB row is a stale session (e.g. the
// account was deleted) — treat it as logged out and clear the stale key.
$navUser = null;
if (!empty($_SESSION['user_id'])) {
    $stmt = grave_db()->prepare('SELECT id, username FROM users WHERE id = :id');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $navUser = $stmt->fetch();

    if (!$navUser) {
        unset($_SESSION['user_id'], $_SESSION['username']);
    }
}
?>
<nav class="site-nav">
  <div class="container site-nav__inner">
    <a href="/" class="site-nav__brand"><img src="/assets/brand.png" alt="THE DEAD LAST" class="site-nav__brand-img"></a>

    <!-- Hamburger toggle: shown only on mobile + tablet (<=1024px) via CSS. -->
    <button type="button" class="site-nav__toggle" id="nav-toggle" aria-expanded="false" aria-controls="nav-collapse" aria-label="Menu">
      <span class="site-nav__toggle-bar" aria-hidden="true"></span>
      <span class="site-nav__toggle-bar" aria-hidden="true"></span>
      <span class="site-nav__toggle-bar" aria-hidden="true"></span>
    </button>

    <!-- Collapsible region: inline on desktop; a dropdown panel under the
         hamburger on mobile/tablet. Holds the menu, search, and auth. -->
    <div class="site-nav__collapse" id="nav-collapse">
      <div class="site-nav__middle">
        <div class="site-nav__links">
          <!-- Desktop: icon-only + stylized tooltip (data-tip). Mobile/tablet
               (<=1024px): icon + label row inside the hamburger panel, no
               tooltips (touch has no hover). -->
          <a href="/" class="site-nav__link site-nav__link--icon" data-tip="Home" aria-label="Home">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/house.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Home</span>
          </a>
          <a href="/game" class="site-nav__link site-nav__link--icon" data-tip="The Game" aria-label="Game">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/gamepad.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Game</span>
          </a>
          <a href="#" class="site-nav__link site-nav__link--icon" data-tip="News" aria-label="News">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/newspaper.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">News</span>
          </a>
          <a href="/media" class="site-nav__link site-nav__link--icon" data-tip="Media" aria-label="Media">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/photo-film.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Media</span>
          </a>
          <a href="/leaderboard" class="site-nav__link site-nav__link--icon" data-tip="Leaderboard" aria-label="Leaderboard">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/ranking-star.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Leaderboard</span>
          </a>
          <a href="/shop" class="site-nav__link site-nav__link--icon" data-tip="Shop" aria-label="Shop">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/bag-shopping.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Shop</span>
          </a>
          <a href="/bbs/" class="site-nav__link site-nav__link--icon" data-tip="Community" aria-label="Community">
            <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/comments.svg" width="19" height="19" alt="" aria-hidden="true">
            <span class="site-nav__label">Community</span>
          </a>
          <?php if ($NAV_ADMIN_URL !== null): ?>
            <a href="<?= htmlspecialchars($NAV_ADMIN_URL) ?>" class="site-nav__link site-nav__link--icon" data-tip="Keeper Admin" aria-label="Admin">
              <img class="site-nav__link-icon" src="https://nerd.biz/assets/fa/svgs/solid/shield-halved.svg" width="19" height="19" alt="" aria-hidden="true">
              <span class="site-nav__label">Admin</span>
            </a>
          <?php endif; ?>
        </div>
        <form class="site-nav__search-form" id="nav-search-form">
          <input class="site-nav__search-input" id="nav-search-input" type="search" aria-label="Search" placeholder="<?= htmlspecialchars($NAV_SEARCH_PLACEHOLDER) ?>">
        </form>
      </div>
      <div class="site-nav__search">
        <button type="button" class="site-nav__search-trigger" id="nav-search-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="nav-search-form" aria-label="Search" title="Search">
          <img class="site-nav__search-icon" src="https://nerd.biz/assets/fa/svgs/solid/magnifying-glass.svg" width="18" height="18" alt="" aria-hidden="true">
        </button>
      </div>
      <div class="site-nav__auth">
        <?php if ($navUser): ?>
          <a href="/bbs/profile/<?= urlencode($navUser['username']) ?>" class="site-nav__link site-nav__username"><?= htmlspecialchars(strtoupper($navUser['username'])) ?></a>
          <a href="/logout" class="btn btn-ghost site-nav__cta">Logout</a>
        <?php else: ?>
          <a href="<?= htmlspecialchars($NAV_LOGIN_URL) ?>" class="btn btn-ghost site-nav__cta">Login</a>
          <a href="<?= htmlspecialchars($NAV_REGISTER_URL) ?>" class="btn btn-primary site-nav__cta">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

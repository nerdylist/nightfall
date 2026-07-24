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
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M3 11.5 12 4l9 7.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 10.5V20h13v-9.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 20v-5h4v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            <span class="site-nav__label">Home</span>
          </a>
          <a href="/game" class="site-nav__link site-nav__link--icon" data-tip="The Game" aria-label="Game">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M6.5 8h11a4.5 4.5 0 0 1 4.4 5.4l-.8 4a2.6 2.6 0 0 1-4.6 1.1L14.6 16H9.4l-1.9 2.5a2.6 2.6 0 0 1-4.6-1.1l-.8-4A4.5 4.5 0 0 1 6.5 8Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8 11v3M6.5 12.5h3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="16" cy="11.6" r="0.6" fill="currentColor" stroke="currentColor"/><circle cx="18" cy="13.4" r="0.6" fill="currentColor" stroke="currentColor"/></svg>
            <span class="site-nav__label">Game</span>
          </a>
          <a href="#" class="site-nav__link site-nav__link--icon" data-tip="News" aria-label="News">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M4 5h13v14a2 2 0 0 0 2 2H6a2 2 0 0 1-2-2V5Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17 9h3v10a2 2 0 0 1-2 2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 9h7M7 13h7M7 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <span class="site-nav__label">News</span>
          </a>
          <a href="/media" class="site-nav__link site-nav__link--icon" data-tip="Media" aria-label="Media">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M7 5v14M17 5v14M3 9h4M3 15h4M17 9h4M17 15h4" stroke="currentColor" stroke-width="2"/><path d="m11 10 3.5 2-3.5 2v-4Z" fill="currentColor" stroke="currentColor" stroke-linejoin="round"/></svg>
            <span class="site-nav__label">Media</span>
          </a>
          <a href="/leaderboard" class="site-nav__link site-nav__link--icon" data-tip="Leaderboard" aria-label="Leaderboard">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M8 21h8M12 17v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 4h10v6a5 5 0 0 1-10 0V4Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M7 6H4v2a4 4 0 0 0 3 3.9M17 6h3v2a4 4 0 0 1-3 3.9" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            <span class="site-nav__label">Leaderboard</span>
          </a>
          <a href="/shop" class="site-nav__link site-nav__link--icon" data-tip="Shop" aria-label="Shop">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M5 8h14l-1 13H6L5 8Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 11V6a3 3 0 0 1 6 0v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <span class="site-nav__label">Shop</span>
          </a>
          <a href="/bbs/" class="site-nav__link site-nav__link--icon" data-tip="Community" aria-label="Community">
            <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M4 5h12v8H8l-4 4V5Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M16 9h4v10l-3-3h-7" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
            <span class="site-nav__label">Community</span>
          </a>
          <?php if ($NAV_ADMIN_URL !== null): ?>
            <a href="<?= htmlspecialchars($NAV_ADMIN_URL) ?>" class="site-nav__link site-nav__link--icon" data-tip="Keeper Admin" aria-label="Admin">
              <svg class="site-nav__link-icon" viewBox="0 0 24 24" width="19" height="19" aria-hidden="true" focusable="false"><path d="M12 3 5 6v5c0 4.4 3 8.4 7 10 4-1.6 7-5.6 7-10V6l-7-3Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9.5 12l2 2 3.5-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
          <svg class="site-nav__search-icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"></circle>
            <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2" stroke-linecap="round"></line>
          </svg>
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

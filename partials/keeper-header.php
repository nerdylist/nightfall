<?php
/**
 * Shared Keeper (admin) shell — full-screen layout with a fixed left sidebar
 * (game logo + grouped nav) and a scrolling center content column. Every
 * keeper page sets $pageTitle / $pageCss, includes this, renders its
 * <main class="keeper-main"> content, then includes keeper-footer.php.
 *
 * Black & white motif. The sidebar's "Forum" group is MOCKED for now — those
 * pages still live under /bbs/admin/ and get folded into Keeper in Phase 2
 * (see docs/backlog.md).
 */
$pageTitle = $pageTitle ?? 'Keeper — The Dead Last Admin';
$pageCss = $pageCss ?? [];

// Current script name, for active-nav highlighting.
$keeperCurrent = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

/** Render one sidebar link, marking it active when it is the current page. */
function keeper_nav_link(string $href, string $label, string $current, bool $external = false): string
{
    $file = basename(parse_url($href, PHP_URL_PATH) ?: $href);
    $isActive = !$external && $file === $current;
    $cls = 'keeper-nav__link' . ($isActive ? ' is-active' : '');
    $aria = $isActive ? ' aria-current="page"' : '';

    return '<a href="' . htmlspecialchars($href) . '" class="' . $cls . '"' . $aria . '>'
        . '<span class="keeper-nav__label">' . htmlspecialchars($label) . '</span></a>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/css/base.css')) ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/css/keeper.css')) ?>">
  <?php foreach ($pageCss as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url($css)) ?>">
  <?php endforeach; ?>
</head>
<body class="keeper">
<div class="keeper-shell">

  <!-- Mobile top bar: brand + hamburger (hidden on desktop). -->
  <div class="keeper-topbar">
    <a href="/keeper/dashboard.php" class="keeper-topbar__brand">
      <img src="/assets/logo.png" alt="THE DEAD LAST" class="keeper-topbar__logo">
    </a>
    <button type="button" class="keeper-topbar__toggle" id="keeper-nav-toggle" aria-expanded="false" aria-controls="keeper-sidebar" aria-label="Menu">
      <span class="keeper-topbar__bar" aria-hidden="true"></span>
      <span class="keeper-topbar__bar" aria-hidden="true"></span>
      <span class="keeper-topbar__bar" aria-hidden="true"></span>
    </button>
  </div>

  <!-- Fixed sidebar navigation. -->
  <aside class="keeper-sidebar" id="keeper-sidebar">
    <a href="/keeper/dashboard.php" class="keeper-brand">
      <img src="/assets/logo.png" alt="THE DEAD LAST" class="keeper-brand__logo">
      <span class="keeper-brand__tag">Keeper</span>
    </a>

    <nav class="keeper-nav">
      <div class="keeper-nav__group">
        <p class="keeper-nav__heading">Overview</p>
        <?= keeper_nav_link('/keeper/dashboard.php', 'Dashboard', $keeperCurrent) ?>
        <?= keeper_nav_link('/keeper/users.php', 'Users', $keeperCurrent) ?>
      </div>

      <div class="keeper-nav__group">
        <p class="keeper-nav__heading">Game</p>
        <?= keeper_nav_link('/keeper/items.php', 'Items', $keeperCurrent) ?>
        <?= keeper_nav_link('/keeper/messages.php', 'NPC Messages', $keeperCurrent) ?>
        <?= keeper_nav_link('/keeper/meshy.php', 'Meshy', $keeperCurrent) ?>
      </div>

      <div class="keeper-nav__group">
        <p class="keeper-nav__heading">Community</p>
        <!-- Forum moderation — mocked; migrates from /bbs/admin/ in Phase 2. -->
        <a href="/bbs/admin/categories.php" class="keeper-nav__link keeper-nav__link--soon">
          <span class="keeper-nav__label">Categories</span><span class="keeper-nav__soon">↗</span>
        </a>
        <a href="/bbs/admin/threads.php" class="keeper-nav__link keeper-nav__link--soon">
          <span class="keeper-nav__label">Threads</span><span class="keeper-nav__soon">↗</span>
        </a>
        <a href="/bbs/admin/chat.php" class="keeper-nav__link keeper-nav__link--soon">
          <span class="keeper-nav__label">Chat</span><span class="keeper-nav__soon">↗</span>
        </a>
      </div>

      <div class="keeper-nav__group">
        <p class="keeper-nav__heading">Config</p>
        <?= keeper_nav_link('/keeper/settings.php', 'Settings', $keeperCurrent) ?>
      </div>
    </nav>

    <div class="keeper-sidebar__footer">
      <a href="/index.php" class="keeper-nav__link keeper-nav__link--muted"><span class="keeper-nav__label">Back to site</span></a>
      <a href="/logout" class="keeper-nav__link keeper-nav__link--muted"><span class="keeper-nav__label">Logout</span></a>
    </div>
  </aside>

  <!-- Backdrop behind the open mobile sidebar. -->
  <div class="keeper-scrim" id="keeper-scrim" hidden></div>

  <!-- Center content column. Pages render <main class="keeper-main"> here. -->
  <div class="keeper-content">

    <!-- Sticky top header — spans the content column between the two sidebars,
         stays pinned as the content scrolls. A page may set $keeperHeaderLeft
         / $keeperHeaderActions (HTML) to fill it. -->
    <header class="keeper-header">
      <div class="keeper-header__left">
        <?= $keeperHeaderLeft ?? '' ?>
      </div>
      <div class="keeper-header__actions">
        <?= $keeperHeaderActions ?? '' ?>
      </div>
    </header>


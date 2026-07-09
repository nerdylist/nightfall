<?php
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
    <a href="/index.php" class="site-nav__brand">THE DEAD LAST</a>
    <div class="site-nav__links">
      <a href="/index.php" class="site-nav__link">Home</a>
      <a href="#" class="site-nav__link">Game</a>
      <a href="#" class="site-nav__link">News</a>
      <a href="/bbs/" class="site-nav__link">Community</a>
      <a href="#" class="site-nav__link">Support</a>
    </div>
    <div class="site-nav__auth">
      <?php if ($navUser): ?>
        <a href="/bbs/profile.php?user=<?= urlencode($navUser['username']) ?>" class="site-nav__link site-nav__username"><?= htmlspecialchars(strtoupper($navUser['username'])) ?></a>
        <a href="/logout.php" class="btn btn-ghost site-nav__cta">Logout</a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-ghost site-nav__cta">Login</a>
        <a href="/register.php" class="btn btn-primary site-nav__cta">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

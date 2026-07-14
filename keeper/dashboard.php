<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

$pageTitle = 'Dashboard — Keeper';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';

$pdo = grave_db();

$totalUsers  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalItems  = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Dashboard</h1>

    <div class="keeper-stats">
      <a href="/keeper/users.php" class="card keeper-stat-tile keeper-stat-tile--link">
        <p class="keeper-stat-tile__label text-muted">Total Users</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalUsers) ?></p>
      </a>
      <a href="/keeper/items.php" class="card keeper-stat-tile keeper-stat-tile--link">
        <p class="keeper-stat-tile__label text-muted">Items</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalItems) ?></p>
      </a>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

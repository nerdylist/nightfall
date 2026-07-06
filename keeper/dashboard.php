<?php
require_once __DIR__ . '/../config.php';

session_start();

if (empty($_SESSION['keeper_admin'])) {
    header('Location: /keeper/index.php');
    exit;
}

$pageTitle = 'Dashboard — Keeper';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';

$pdo = grave_db();

$totalUsers = (int) $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'];

$stmt = $pdo->query('SELECT id, email, username, created_at FROM users ORDER BY created_at DESC');
$registeredUsers = $stmt->fetchAll();
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Dashboard</h1>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Total Registered Users</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalUsers) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Registered Users</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>Email</th>
              <th>Username</th>
              <th>Created Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($registeredUsers as $user): ?>
            <tr>
              <td><?= htmlspecialchars($user['email']) ?></td>
              <td><?= htmlspecialchars($user['username']) ?></td>
              <td><?= htmlspecialchars($user['created_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

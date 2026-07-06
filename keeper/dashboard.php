<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Dashboard — Keeper';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';

// Real session gating goes here in the wiring pass — for the prototype
// this page renders directly as the "logged in" state.

// Placeholder data only — not read from any database.
$totalUsers = 128;
$fakeUsers = [
    ['email' => 'ashwood.mara@example.com', 'username' => 'maraash', 'created_at' => '2026-06-02 14:12'],
    ['email' => 'grady.holt@example.com', 'username' => 'holtgrady', 'created_at' => '2026-06-05 09:47'],
    ['email' => 'delacroix.jean@example.com', 'username' => 'jdelacroix', 'created_at' => '2026-06-11 22:03'],
    ['email' => 'winters.rae@example.com', 'username' => 'raewinters', 'created_at' => '2026-06-14 17:29'],
    ['email' => 'oc.finn@example.com', 'username' => 'finnoc', 'created_at' => '2026-06-20 08:55'],
    ['email' => 'blackwood.eli@example.com', 'username' => 'eliblackwood', 'created_at' => '2026-06-25 13:41'],
    ['email' => 'sato.yumi@example.com', 'username' => 'yumisato', 'created_at' => '2026-07-01 19:16'],
];
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
            <?php foreach ($fakeUsers as $user): ?>
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

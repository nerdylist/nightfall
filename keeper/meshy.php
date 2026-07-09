<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['keeper_admin'])) {
    header('Location: /keeper/index.php');
    exit;
}

$pageTitle = 'Meshy Backlog — Keeper';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';

$pdo = grave_db();

// meshy_tasks may not exist yet if the migration hasn't run — degrade gracefully.
$tasks = [];
$pending = 0;
try {
    $tasks = $pdo->query(
        'SELECT task_id, task_type, status, progress, consumed_at, updated_at
         FROM meshy_tasks ORDER BY updated_at DESC LIMIT 100'
    )->fetchAll();
    $pending = (int) $pdo->query(
        "SELECT COUNT(*) AS c FROM meshy_tasks
         WHERE status = 'SUCCEEDED' AND consumed_at IS NULL"
    )->fetch()['c'];
} catch (Throwable $e) {
    $tasks = null; // signals "table missing"
}
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Meshy Backlog</h1>

    <?php if ($tasks === null): ?>
    <div class="card keeper-table-card">
      <p class="text-muted">The <code>meshy_tasks</code> table doesn't exist yet. Run
      <code>php web/bin/setup-db.php</code> to apply the migration.</p>
    </div>
    <?php else: ?>
    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Completed &amp; Unpulled</p>
        <p class="keeper-stat-tile__value"><?= number_format($pending) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Recent Tasks</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>Task ID</th>
              <th>Type</th>
              <th>Status</th>
              <th>Progress</th>
              <th>Pulled</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
            <tr>
              <td><?= htmlspecialchars($t['task_id']) ?></td>
              <td><?= htmlspecialchars((string) $t['task_type']) ?></td>
              <td><?= htmlspecialchars((string) $t['status']) ?></td>
              <td><?= (int) $t['progress'] ?>%</td>
              <td><?= $t['consumed_at'] ? 'yes' : '&mdash;' ?></td>
              <td><?= htmlspecialchars((string) $t['updated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($tasks)): ?>
            <tr><td colspan="6" class="text-muted">No Meshy tasks recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

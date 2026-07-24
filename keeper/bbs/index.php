<?php
require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

require_once __DIR__ . '/_forum.php';

/**
 * Keeper > Forum — Dashboard. Read-only counts + recent activity, ported from
 * bbs/admin/index.php. Forum tables live in bbs/forum.db; user names come from
 * the attached host.users.
 */
$db = keeper_bbs_db();

$counts = [
    'users'    => (int) $db->query('SELECT COUNT(*) FROM host.users')->fetchColumn(),
    'categories' => (int) $db->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'threads'  => (int) $db->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
    'posts'    => (int) $db->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'chat'     => (int) $db->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn(),
    'reactions' => (int) $db->query('SELECT COUNT(*) FROM reactions')->fetchColumn(),
];

$recentUsers = $db->query(
    "SELECT id, COALESCE(NULLIF(display_name,''),username) AS display_name, username, role, status,
            COALESCE(join_date,date(created_at)) AS join_date
     FROM host.users ORDER BY id DESC LIMIT 5"
)->fetchAll();

$recentThreads = $db->query(
    "SELECT t.id, t.title, c.name AS category_name,
            COALESCE(NULLIF(u.display_name,''),u.username) AS author_name
     FROM threads t
     JOIN categories c ON c.id = t.category_id
     JOIN host.users u ON u.id = t.author_id
     ORDER BY t.id DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Forum — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Forum</h1>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Users</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['users']) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Categories</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['categories']) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Threads</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['threads']) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Posts</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['posts']) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Chat Messages</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['chat']) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Reactions</p>
        <p class="keeper-stat-tile__value"><?= number_format($counts['reactions']) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Recent Signups</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead><tr><th>ID</th><th>User</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
          <tbody>
            <?php foreach ($recentUsers as $u): ?>
            <tr>
              <td><?= (int) $u['id'] ?></td>
              <td><?= htmlspecialchars((string) $u['display_name']) ?></td>
              <td><?= htmlspecialchars((string) $u['role']) ?></td>
              <td><?= htmlspecialchars((string) $u['status']) ?></td>
              <td><?= htmlspecialchars((string) $u['join_date']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentUsers)): ?><tr><td colspan="5" class="text-muted">No users.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Recent Threads</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Author</th></tr></thead>
          <tbody>
            <?php foreach ($recentThreads as $t): ?>
            <tr>
              <td><?= (int) $t['id'] ?></td>
              <td><a class="keeper-bbs-link" href="/keeper/bbs/thread-edit.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars((string) $t['title']) ?></a></td>
              <td><?= htmlspecialchars((string) $t['category_name']) ?></td>
              <td><?= htmlspecialchars((string) $t['author_name']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($recentThreads)): ?><tr><td colspan="4" class="text-muted">No threads.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

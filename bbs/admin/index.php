<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();

$counts = [
    'Users'         => (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'Categories'    => (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'Threads'       => (int)$db->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
    'Posts'         => (int)$db->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'Chat Messages' => (int)$db->query('SELECT COUNT(*) FROM chat_messages')->fetchColumn(),
    'Reactions'     => (int)$db->query('SELECT COUNT(*) FROM reactions')->fetchColumn(),
];

$recentUsers = $db->query('SELECT id, display_name, username, role, status, join_date FROM users ORDER BY id DESC LIMIT 5')->fetchAll();

$recentThreads = $db->query(
    'SELECT t.id, t.title, c.name AS category_name, u.display_name AS author_name
     FROM threads t
     JOIN categories c ON c.id = t.category_id
     JOIN users u ON u.id = t.author_id
     ORDER BY t.id DESC LIMIT 5'
)->fetchAll();

$active = 'dashboard';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head"><h1>Dashboard</h1></div>

  <div class="stat-grid">
    <?php foreach ($counts as $label => $num): ?>
      <div class="stat-tile">
        <div class="stat-num"><?= adm_e($num) ?></div>
        <div class="stat-label"><?= adm_e($label) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="admin-page-head mt-6"><h2>Recent signups</h2></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Username</th>
          <th>Role</th>
          <th>Status</th>
          <th>Joined</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentUsers as $u): ?>
          <tr>
            <td>
              <div class="admin-cell-user">
                <?php render_avatar($u['display_name'], 32); ?>
                <span><?= adm_e($u['display_name']) ?></span>
              </div>
            </td>
            <td><?= adm_e($u['username']) ?></td>
            <td>
              <?php if ($u['role'] === 'admin'): ?>
                <span class="badge badge-admin">admin</span>
              <?php else: ?>
                <span class="badge">user</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['status'] === 'banned'): ?>
                <span class="badge badge-banned">banned</span>
              <?php else: ?>
                <span class="badge">active</span>
              <?php endif; ?>
            </td>
            <td><?= adm_e($u['join_date']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="admin-page-head mt-6"><h2>Recent threads</h2></div>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Title</th>
          <th>Category</th>
          <th>Author</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentThreads as $t): ?>
          <tr>
            <td><?= adm_e($t['title']) ?></td>
            <td><?= adm_e($t['category_name']) ?></td>
            <td><?= adm_e($t['author_name']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

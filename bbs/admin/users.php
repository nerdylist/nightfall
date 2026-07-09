<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();
$selfId = (int)auth_current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/users.php');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        adm_flash('error', 'User not found.');
        adm_redirect('/bbs/admin/users.php');
    }

    $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
    $isLastActiveAdmin = ($target['role'] === 'admin' && $target['status'] === 'active' && $adminCount <= 1);

    if ($action === 'toggle_role') {
        if ($target['role'] === 'admin') {
            if ($target['id'] == $selfId) {
                adm_flash('error', 'You cannot demote yourself.');
                adm_redirect('/bbs/admin/users.php');
            }
            if ($isLastActiveAdmin) {
                adm_flash('error', 'Cannot demote the last active admin.');
                adm_redirect('/bbs/admin/users.php');
            }
        }
        $newRole = ($target['role'] === 'admin') ? 'user' : 'admin';
        $upd = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $upd->execute([$newRole, $id]);
        adm_flash('success', 'User role updated.');
        adm_redirect('/bbs/admin/users.php');
    } elseif ($action === 'toggle_status') {
        if ($target['status'] === 'active') {
            if ($target['id'] == $selfId) {
                adm_flash('error', 'You cannot ban yourself.');
                adm_redirect('/bbs/admin/users.php');
            }
        }
        $newStatus = ($target['status'] === 'active') ? 'banned' : 'active';
        $upd = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $upd->execute([$newStatus, $id]);
        adm_flash('success', 'User status updated.');
        adm_redirect('/bbs/admin/users.php');
    } elseif ($action === 'delete') {
        if ($target['id'] == $selfId) {
            adm_flash('error', 'Cannot delete yourself.');
            adm_redirect('/bbs/admin/users.php');
        }
        if ($isLastActiveAdmin) {
            adm_flash('error', 'Cannot delete the last active admin.');
            adm_redirect('/bbs/admin/users.php');
        }
        $cStmt = $db->prepare(
            'SELECT (SELECT COUNT(*) FROM threads WHERE author_id = ?)
                  + (SELECT COUNT(*) FROM posts WHERE author_id = ?)
                  + (SELECT COUNT(*) FROM chat_messages WHERE author_id = ?)
                  + (SELECT COUNT(*) FROM reactions WHERE user_id = ?)'
        );
        $cStmt->execute([$id, $id, $id, $id]);
        $contentCount = (int)$cStmt->fetchColumn();
        if ($contentCount > 0) {
            adm_flash('error', 'User has content (threads/posts/chat/reactions) and cannot be deleted.');
            adm_redirect('/bbs/admin/users.php');
        }
        $del = $db->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
        adm_flash('success', 'User deleted.');
        adm_redirect('/bbs/admin/users.php');
    }

    adm_redirect('/bbs/admin/users.php');
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $stmt = $db->prepare(
        'SELECT * FROM users
         WHERE username LIKE ? OR display_name LIKE ? OR email LIKE ?
         ORDER BY id'
    );
    $like = '%' . $q . '%';
    $stmt->execute([$like, $like, $like]);
    $users = $stmt->fetchAll();
} else {
    $users = $db->query('SELECT * FROM users ORDER BY id')->fetchAll();
}

$active = 'users';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head"><h1>Users</h1></div>

  <form method="get" action="/bbs/admin/users.php" class="flex gap-2 mb-4">
    <input class="input" type="text" name="q" value="<?= adm_e($q) ?>" placeholder="Search username, name, or email...">
    <button class="btn btn-primary" type="submit">Search</button>
  </form>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>User</th>
          <th>Username</th>
          <th>Display Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Status</th>
          <th>Reputation</th>
          <th>Joined</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u):
            $uid = (int)$u['id'];
            $isSelf = ($uid === $selfId);
        ?>
          <tr>
            <td><?= adm_e($uid) ?></td>
            <td>
              <div class="admin-cell-user">
                <?php render_avatar($u['display_name'], 32); ?>
              </div>
            </td>
            <td><?= adm_e($u['username']) ?></td>
            <td><?= adm_e($u['display_name']) ?></td>
            <td><?= adm_e($u['email']) ?></td>
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
            <td><?= adm_e($u['reputation']) ?></td>
            <td><?= adm_e($u['join_date'] !== null && $u['join_date'] !== '' ? $u['join_date'] : $u['created_at']) ?></td>
            <td>
              <div class="action-group">
                <?php if (!$isSelf): ?>
                  <form method="post" action="/bbs/admin/users.php" class="action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_role">
                    <input type="hidden" name="id" value="<?= adm_e($uid) ?>">
                    <button class="btn btn-sm" type="submit"><?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?></button>
                  </form>
                  <form method="post" action="/bbs/admin/users.php" class="action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= adm_e($uid) ?>">
                    <button class="btn btn-sm" type="submit"><?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?></button>
                  </form>
                <?php endif; ?>
                <a class="btn btn-sm" href="/bbs/admin/user-edit.php?id=<?= adm_e($uid) ?>">Edit</a>
                <?php if (!$isSelf): ?>
                  <form method="post" action="/bbs/admin/users.php" class="action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= adm_e($uid) ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this user?">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();
$selfId = (int)auth_current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/users.php');
    }

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        adm_flash('error', 'User not found.');
        adm_redirect('/bbs/admin/users.php');
    }

    $role = $_POST['role'] ?? '';
    if (!in_array($role, ['user', 'admin'], true)) {
        adm_flash('error', 'Invalid role.');
        adm_redirect('/bbs/admin/users.php');
    }

    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['active', 'banned'], true)) {
        adm_flash('error', 'Invalid status.');
        adm_redirect('/bbs/admin/users.php');
    }

    $reputation = (int)($_POST['reputation'] ?? 0);
    $displayName = trim((string)($_POST['display_name'] ?? ''));
    $bio = (string)($_POST['bio'] ?? '');

    if ($id === $selfId && ($role === 'user' || $status === 'banned')) {
        adm_flash('error', 'You cannot demote or ban yourself.');
        adm_redirect('/bbs/admin/users.php');
    }

    if ($target['role'] === 'admin' && $target['status'] === 'active' && $role === 'user') {
        $adminCount = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
        if ($adminCount <= 1) {
            adm_flash('error', 'Cannot demote the last active admin.');
            adm_redirect('/bbs/admin/users.php');
        }
    }

    $upd = $db->prepare('UPDATE users SET display_name = ?, bio = ?, role = ?, status = ?, reputation = ? WHERE id = ?');
    $upd->execute([$displayName, $bio, $role, $status, $reputation, $id]);

    adm_flash('success', 'User updated.');
    adm_redirect('/bbs/admin/users.php');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    adm_flash('error', 'User not found.');
    adm_redirect('/bbs/admin/users.php');
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

  <div class="admin-page-head"><h1>Edit User</h1></div>

  <form method="post" action="/bbs/admin/user-edit.php?id=<?= adm_e($id) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= adm_e($id) ?>">
    <div class="settings-group">
      <div class="form-grid">
        <div class="field">
          <label>Display name</label>
          <input class="input" name="display_name" value="<?= adm_e($user['display_name']) ?>">
        </div>
        <div class="field">
          <label>Reputation</label>
          <input class="input" type="number" name="reputation" value="<?= adm_e($user['reputation']) ?>">
        </div>
        <div class="field">
          <label>Role</label>
          <select class="input" name="role">
            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="field">
          <label>Status</label>
          <select class="input" name="status">
            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>Banned</option>
          </select>
        </div>
        <div class="field full-width">
          <label>Bio</label>
          <textarea class="textarea" name="bio"><?= adm_e($user['bio']) ?></textarea>
        </div>
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/bbs/admin/users.php">Cancel</a>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </div>
  </form>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

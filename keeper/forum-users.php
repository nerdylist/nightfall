<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

// Single userbase: forum users ARE host users (data/graverising.sqlite),
// with the forum's profile/moderation columns absorbed into the host users
// table (migrations/004_forum_user_columns.sql). Keeper manages them through
// the regular host connection — no forum DB access needed here.

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/forum-users.php');
        exit;
    }

    $db = grave_db();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        $_SESSION['keeper_flash'] = 'Forum user not found.';
        header('Location: /keeper/forum-users.php');
        exit;
    }

    $adminCount = (int) $db->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"
    )->fetchColumn();
    $isLastActiveAdmin = ($target['role'] === 'admin' && $target['status'] === 'active' && $adminCount <= 1);

    if ($action === 'toggle_role') {
        if ($target['role'] === 'admin' && $isLastActiveAdmin) {
            $_SESSION['keeper_flash'] = 'Cannot demote the last active admin.';
            header('Location: /keeper/forum-users.php');
            exit;
        }
        $newRole = ($target['role'] === 'admin') ? 'user' : 'admin';
        $upd = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $upd->execute([$newRole, $id]);
        $_SESSION['keeper_flash'] = 'Forum user role updated.';
        header('Location: /keeper/forum-users.php');
        exit;
    }

    if ($action === 'toggle_status') {
        if ($target['status'] === 'active' && $isLastActiveAdmin) {
            $_SESSION['keeper_flash'] = 'Cannot ban the last active admin.';
            header('Location: /keeper/forum-users.php');
            exit;
        }
        $newStatus = ($target['status'] === 'active') ? 'banned' : 'active';
        $upd = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $upd->execute([$newStatus, $id]);
        $_SESSION['keeper_flash'] = 'Forum user status updated.';
        header('Location: /keeper/forum-users.php');
        exit;
    }

    header('Location: /keeper/forum-users.php');
    exit;
}

$pageTitle = 'Forum Users — Keeper';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$db = grave_db();

$totalForumUsers = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

$forumUsers = $db->query(
    "SELECT id, username,
            COALESCE(NULLIF(display_name, ''), username) AS display_name,
            email, role, status, reputation, threads_started,
            COALESCE(join_date, date(created_at)) AS join_date
     FROM users ORDER BY id"
)->fetchAll();
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Forum Users</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Total Forum Users</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalForumUsers) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Forum Users</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Display Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Reputation</th>
              <th>Threads</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($forumUsers as $u):
                $uid = (int) $u['id'];
            ?>
            <tr>
              <td><?= $uid ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars((string) $u['display_name']) ?></td>
              <td><?= htmlspecialchars((string) $u['email']) ?></td>
              <td><?= htmlspecialchars($u['role']) ?></td>
              <td><?= htmlspecialchars($u['status']) ?></td>
              <td><?= (int) $u['reputation'] ?></td>
              <td><?= (int) $u['threads_started'] ?></td>
              <td><?= htmlspecialchars((string) $u['join_date']) ?></td>
              <td>
                <div class="keeper-action-group">
                  <form method="post" action="/keeper/forum-users.php">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="toggle_role">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button class="btn" type="submit"><?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?></button>
                  </form>
                  <form method="post" action="/keeper/forum-users.php">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button class="btn" type="submit"><?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($forumUsers)): ?>
            <tr><td colspan="10" class="text-muted">No forum users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

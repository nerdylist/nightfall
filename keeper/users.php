<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

/**
 * Keeper > Users — the single user-management screen.
 *
 * There is ONE userbase: the host `users` table (data/graverising.sqlite) serves
 * both the site and the forum, with the forum's profile/moderation columns
 * absorbed into it (migrations/004_forum_user_columns.sql). This page manages
 * every user (promote/demote admin role, ban/unban) — it replaces the old
 * "Forum Users" screen and the dashboard's read-only user list.
 */

/**
 * Editable player_stats columns (the game data an admin adjusts). Grouped for
 * the modal layout: [label => column]. Derived/achievement-timer columns are
 * intentionally omitted to keep the form manageable; add here if needed.
 */
function keeper_stat_groups(): array
{
    return [
        'Kills' => [
            'humans_killed' => 'Humans Killed', 'zombies_killed' => 'Zombies Killed',
            'kills_hvz' => 'Human→Zombie', 'kills_hvh' => 'Human→Human',
            'kills_zvz' => 'Zombie→Zombie', 'kills_zvh' => 'Zombie→Human',
            'bat_kills' => 'Bat Kills',
        ],
        'Life & Death' => [
            'deaths' => 'Deaths', 'true_deaths' => 'True Deaths', 'redemptions' => 'Redemptions',
            'times_turned' => 'Times Turned', 'humans_infected' => 'Humans Infected', 'lives' => 'Lives',
        ],
        'Economy & Progress' => [
            'bank' => 'Bank (balance)', 'banked_total' => 'Banked Total', 'chests_looted' => 'Chests Looted',
            'biggest_horde_size' => 'Biggest Horde', 'longest_life_seconds' => 'Longest Life (s)',
            'playtime_seconds' => 'Playtime (s)', 'distance_m' => 'Distance (m)',
        ],
    ];
}

/** Flat whitelist of editable stat columns. */
function keeper_stat_columns(): array
{
    $out = [];
    foreach (keeper_stat_groups() as $group) {
        foreach ($group as $col => $label) { $out[] = $col; }
    }
    return $out;
}

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/users.php');
        exit;
    }

    $db = grave_db();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        $_SESSION['keeper_flash'] = 'User not found.';
        header('Location: /keeper/users.php');
        exit;
    }

    $adminCount = (int) $db->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'"
    )->fetchColumn();
    $isLastActiveAdmin = ($target['role'] === 'admin' && $target['status'] === 'active' && $adminCount <= 1);

    if ($action === 'toggle_role') {
        if ($target['role'] === 'admin' && $isLastActiveAdmin) {
            $_SESSION['keeper_flash'] = 'Cannot demote the last active admin.';
            header('Location: /keeper/users.php');
            exit;
        }
        $newRole = ($target['role'] === 'admin') ? 'user' : 'admin';
        $upd = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $upd->execute([$newRole, $id]);
        $_SESSION['keeper_flash'] = 'User role updated.';
        header('Location: /keeper/users.php');
        exit;
    }

    if ($action === 'toggle_status') {
        if ($target['status'] === 'active' && $isLastActiveAdmin) {
            $_SESSION['keeper_flash'] = 'Cannot ban the last active admin.';
            header('Location: /keeper/users.php');
            exit;
        }
        $newStatus = ($target['status'] === 'active') ? 'banned' : 'active';
        $upd = $db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $upd->execute([$newStatus, $id]);
        $_SESSION['keeper_flash'] = 'User status updated.';
        header('Location: /keeper/users.php');
        exit;
    }

    // --- Edit all fields of a user ---
    if ($action === 'edit_user') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $display  = trim((string) ($_POST['display_name'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $status   = ($_POST['status'] ?? 'active') === 'banned' ? 'banned' : 'active';
        $rep      = (int) ($_POST['reputation'] ?? 0);

        $errors = [];
        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email is required.'; }

        // Demoting/banning the last active admin is not allowed.
        if (!$errors && $isLastActiveAdmin && ($role !== 'admin' || $status !== 'active')) {
            $errors[] = 'Cannot remove admin/active from the last active admin.';
        }

        // Username / email must stay unique (excluding this user).
        if (!$errors) {
            $chk = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ? AND id != ?');
            $chk->execute([$username, $id]);
            if ((int) $chk->fetchColumn() > 0) { $errors[] = 'That username is taken.'; }
        }
        if (!$errors) {
            $chk = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id != ?');
            $chk->execute([$email, $id]);
            if ((int) $chk->fetchColumn() > 0) { $errors[] = 'That email is taken.'; }
        }

        if ($errors) {
            $_SESSION['keeper_flash'] = implode(' ', $errors);
            header('Location: /keeper/users.php');
            exit;
        }

        $upd = $db->prepare(
            'UPDATE users SET username = ?, display_name = ?, email = ?, role = ?, status = ?, reputation = ? WHERE id = ?'
        );
        $upd->execute([$username, $display, $email, $role, $status, $rep, $id]);
        $_SESSION['keeper_flash'] = 'User updated.';
        header('Location: /keeper/users.php');
        exit;
    }

    // --- Reset a user's password ---
    if ($action === 'reset_password') {
        $pw = (string) ($_POST['new_password'] ?? '');
        if (strlen($pw) < 8) {
            $_SESSION['keeper_flash'] = 'New password must be at least 8 characters.';
            header('Location: /keeper/users.php');
            exit;
        }
        $hash = password_hash($pw, PASSWORD_DEFAULT);
        $upd = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$hash, $id]);
        $_SESSION['keeper_flash'] = 'Password reset for user #' . $id . '.';
        header('Location: /keeper/users.php');
        exit;
    }

    // --- Delete a user (hard delete; cascades stats/characters/tokens) ---
    if ($action === 'delete_user') {
        if ($id === (int) ($_SESSION['user_id'] ?? 0)) {
            $_SESSION['keeper_flash'] = 'You cannot delete your own account.';
            header('Location: /keeper/users.php');
            exit;
        }
        if ($isLastActiveAdmin) {
            $_SESSION['keeper_flash'] = 'Cannot delete the last active admin.';
            header('Location: /keeper/users.php');
            exit;
        }
        $db->exec('PRAGMA foreign_keys = ON');
        $del = $db->prepare('DELETE FROM users WHERE id = ?');
        $del->execute([$id]);
        $_SESSION['keeper_flash'] = 'User deleted.';
        header('Location: /keeper/users.php');
        exit;
    }

    // --- Edit a user's game data (player_stats) ---
    if ($action === 'edit_stats') {
        $cols = keeper_stat_columns(); // whitelist
        $sets = [];
        $vals = [];
        foreach ($cols as $c) {
            if (array_key_exists($c, $_POST)) {
                $sets[] = "$c = ?";
                $vals[] = max(0, (int) $_POST[$c]);
            }
        }
        if ($sets) {
            // Ensure a player_stats row exists, then update the posted columns.
            $ins = $db->prepare('INSERT OR IGNORE INTO player_stats (user_id) VALUES (?)');
            $ins->execute([$id]);
            $vals[] = $id;
            $upd = $db->prepare('UPDATE player_stats SET ' . implode(', ', $sets) . ', updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');
            $upd->execute($vals);
        }
        $_SESSION['keeper_flash'] = 'Game data updated for user #' . $id . '.';
        header('Location: /keeper/users.php');
        exit;
    }

    header('Location: /keeper/users.php');
    exit;
}

$pageTitle = 'Users — Keeper';
$pageCss = ['/css/keeper-users.css'];
$pageJs = ['/js/keeper-users.js'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$db = grave_db();

$totalUsers  = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$adminUsers  = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
$bannedUsers = (int) $db->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn();

// Full rows (raw display_name too, for prefilling the edit form).
$users = $db->query(
    "SELECT id, username, display_name, email, role, status, reputation, threads_started,
            COALESCE(join_date, date(created_at)) AS join_date
     FROM users ORDER BY id"
)->fetchAll();

$selfId = (int) ($_SESSION['user_id'] ?? 0);

// Preload player_stats keyed by user_id for the game-data modal prefill.
$statCols = keeper_stat_columns();
$statsByUser = [];
foreach ($db->query('SELECT * FROM player_stats')->fetchAll() as $row) {
    $statsByUser[(int) $row['user_id']] = $row;
}
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Users</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Total Users</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalUsers) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Admins</p>
        <p class="keeper-stat-tile__value"><?= number_format($adminUsers) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Banned</p>
        <p class="keeper-stat-tile__value"><?= number_format($bannedUsers) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">All Users</h2>
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
            <?php foreach ($users as $u):
                $uid = (int) $u['id'];
                $shown = ($u['display_name'] !== null && $u['display_name'] !== '') ? $u['display_name'] : $u['username'];
                // JSON payload for the edit modal (prefill via JS).
                $payload = htmlspecialchars(json_encode([
                    'id' => $uid,
                    'username' => (string) $u['username'],
                    'display_name' => (string) $u['display_name'],
                    'email' => (string) $u['email'],
                    'role' => (string) $u['role'],
                    'status' => (string) $u['status'],
                    'reputation' => (int) $u['reputation'],
                ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <tr>
              <td><?= $uid ?></td>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= htmlspecialchars((string) $shown) ?></td>
              <td><?= htmlspecialchars((string) $u['email']) ?></td>
              <td><?= htmlspecialchars($u['role']) ?></td>
              <td><?= htmlspecialchars($u['status']) ?></td>
              <td><?= (int) $u['reputation'] ?></td>
              <td><?= (int) $u['threads_started'] ?></td>
              <td><?= htmlspecialchars((string) $u['join_date']) ?></td>
              <td>
                <?php
                // Stats payload for the game-data modal (0-filled if no row).
                $srow = $statsByUser[$uid] ?? [];
                $stats = ['id' => $uid, 'username' => (string) $u['username']];
                foreach ($statCols as $c) { $stats[$c] = (int) ($srow[$c] ?? 0); }
                $statsPayload = htmlspecialchars(json_encode($stats), ENT_QUOTES);
                ?>
                <div class="keeper-action-group keeper-users-actions">
                  <button type="button" class="btn" data-edit-user="<?= $payload ?>">Edit</button>
                  <button type="button" class="btn" data-edit-stats="<?= $statsPayload ?>">Game Data</button>
                  <form method="post" action="/keeper/users.php" class="keeper-users-inline">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="toggle_role">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button class="btn" type="submit"><?= $u['role'] === 'admin' ? 'Demote' : 'Promote' ?></button>
                  </form>
                  <form method="post" action="/keeper/users.php" class="keeper-users-inline">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button class="btn" type="submit"><?= $u['status'] === 'active' ? 'Ban' : 'Unban' ?></button>
                  </form>
                  <?php if ($uid !== $selfId): ?>
                  <form method="post" action="/keeper/users.php" class="keeper-users-inline" onsubmit="return confirm('Delete <?= htmlspecialchars($u['username'], ENT_QUOTES) ?> permanently? This removes their stats, characters, and forum content.');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="id" value="<?= $uid ?>">
                    <button class="btn keeper-users-danger" type="submit">Delete</button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="10" class="text-muted">No users found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Account edit modal (prefilled via JS from the row's data-edit-user). -->
  <div class="keeper-modal" id="user-modal" role="dialog" aria-modal="true" aria-labelledby="user-modal-title" hidden>
    <div class="keeper-modal__backdrop" data-close-user-modal></div>
    <div class="keeper-modal__panel">
      <div class="keeper-modal__head">
        <h2 class="keeper-modal__title" id="user-modal-title">Edit User</h2>
        <button type="button" class="keeper-modal__close" data-close-user-modal aria-label="Close">&times;</button>
      </div>
      <form method="post" action="/keeper/users.php" class="keeper-users-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id" id="eu-id" value="">
        <div class="keeper-users-grid">
          <label class="keeper-users-field">
            <span class="keeper-users-label">Username</span>
            <input class="field" type="text" name="username" id="eu-username" required>
          </label>
          <label class="keeper-users-field">
            <span class="keeper-users-label">Display Name</span>
            <input class="field" type="text" name="display_name" id="eu-display">
          </label>
          <label class="keeper-users-field keeper-users-field--wide">
            <span class="keeper-users-label">Email</span>
            <input class="field" type="email" name="email" id="eu-email" required>
          </label>
          <label class="keeper-users-field">
            <span class="keeper-users-label">Role</span>
            <select class="field" name="role" id="eu-role">
              <option value="user">user</option>
              <option value="admin">admin</option>
            </select>
          </label>
          <label class="keeper-users-field">
            <span class="keeper-users-label">Status</span>
            <select class="field" name="status" id="eu-status">
              <option value="active">active</option>
              <option value="banned">banned</option>
            </select>
          </label>
          <label class="keeper-users-field">
            <span class="keeper-users-label">Reputation</span>
            <input class="field" type="number" name="reputation" id="eu-reputation" value="0">
          </label>
        </div>
        <div class="keeper-users-modal-actions">
          <button type="button" class="btn btn-ghost" data-close-user-modal>Cancel</button>
          <button type="submit" class="btn btn-primary">Save User</button>
        </div>
      </form>

      <hr class="keeper-users-hr">

      <form method="post" action="/keeper/users.php" class="keeper-users-form keeper-users-reset">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="id" id="rp-id" value="">
        <label class="keeper-users-field keeper-users-field--wide">
          <span class="keeper-users-label">Reset Password (min 8 chars)</span>
          <input class="field" type="text" name="new_password" placeholder="New password" autocomplete="off">
        </label>
        <div class="keeper-users-modal-actions">
          <button type="submit" class="btn">Set Password</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Game data (player_stats) modal — prefilled via data-edit-stats. -->
  <div class="keeper-modal" id="stats-modal" role="dialog" aria-modal="true" aria-labelledby="stats-modal-title" hidden>
    <div class="keeper-modal__backdrop" data-close-stats-modal></div>
    <div class="keeper-modal__panel keeper-modal__panel--wide">
      <div class="keeper-modal__head">
        <h2 class="keeper-modal__title" id="stats-modal-title">Game Data</h2>
        <button type="button" class="keeper-modal__close" data-close-stats-modal aria-label="Close">&times;</button>
      </div>
      <p class="text-muted keeper-users-hint">Editing player stats for <strong id="es-username"></strong>. Values are whole numbers.</p>
      <form method="post" action="/keeper/users.php" class="keeper-users-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="edit_stats">
        <input type="hidden" name="id" id="es-id" value="">
        <?php foreach (keeper_stat_groups() as $groupName => $group): ?>
        <fieldset class="keeper-users-statgroup">
          <legend><?= htmlspecialchars($groupName) ?></legend>
          <div class="keeper-users-statgrid">
            <?php foreach ($group as $col => $label): ?>
            <label class="keeper-users-field">
              <span class="keeper-users-label"><?= htmlspecialchars($label) ?></span>
              <input class="field" type="number" min="0" name="<?= htmlspecialchars($col) ?>" id="es-<?= htmlspecialchars($col) ?>" value="0">
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endforeach; ?>
        <div class="keeper-users-modal-actions">
          <button type="button" class="btn btn-ghost" data-close-stats-modal>Cancel</button>
          <button type="submit" class="btn btn-primary">Save Game Data</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

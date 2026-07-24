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

/** Keeper > Forum > Chat — chat + reaction moderation. Ported from bbs/admin/chat.php. */
$keeperCsrf = keeper_bbs_csrf();
$db = keeper_bbs_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/bbs/chat.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_chat') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM chat_messages WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['keeper_flash'] = 'Chat message deleted.';
        header('Location: /keeper/bbs/chat.php');
        exit;
    } elseif ($action === 'delete_reaction') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM reactions WHERE id = ?');
        $stmt->execute([$id]);
        $_SESSION['keeper_flash'] = 'Reaction deleted.';
        header('Location: /keeper/bbs/chat.php');
        exit;
    }

    header('Location: /keeper/bbs/chat.php');
    exit;
}

$chatMessages = $db->query(
    "SELECT cm.*, t.title AS thread_title,
            COALESCE(NULLIF(u.display_name, ''), u.username) AS author_name
     FROM chat_messages cm
     JOIN threads t ON t.id = cm.thread_id
     JOIN host.users u ON u.id = cm.author_id
     ORDER BY cm.id DESC
     LIMIT 50"
)->fetchAll();

$reactionSummary = $db->query(
    'SELECT emoji, COUNT(*) AS c FROM reactions GROUP BY emoji ORDER BY c DESC'
)->fetchAll();

$reactions = $db->query(
    "SELECT r.*, COALESCE(NULLIF(u.display_name, ''), u.username) AS user_name,
            t.title AS thread_title
     FROM reactions r
     JOIN host.users u ON u.id = r.user_id
     LEFT JOIN posts p ON p.id = r.post_id
     LEFT JOIN threads t ON t.id = p.thread_id
     ORDER BY r.id DESC
     LIMIT 50"
)->fetchAll();

$pageTitle = 'Forum Chat — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Forum Chat</h1>

    <?php if ($flash): ?><p class="keeper-flash"><?= htmlspecialchars($flash) ?></p><?php endif; ?>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Recent Chat <span class="keeper-bbs-count"><?= count($chatMessages) ?></span></h2>
      <?php if (empty($chatMessages)): ?>
        <p class="text-muted">No chat messages.</p>
      <?php else: ?>
        <div class="keeper-table-scroll">
          <table class="keeper-table">
            <thead><tr><th>Thread</th><th>Author</th><th>Message</th><th>Time</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($chatMessages as $cm): ?>
              <tr>
                <td><?= htmlspecialchars((string) $cm['thread_title']) ?></td>
                <td><?= htmlspecialchars((string) $cm['author_name']) ?></td>
                <td class="keeper-bbs-desc"><?= htmlspecialchars((string) $cm['text']) ?></td>
                <td><?= htmlspecialchars((string) $cm['timestamp']) ?></td>
                <td>
                  <form method="post" action="/keeper/bbs/chat.php" onsubmit="return confirm('Delete this chat message?');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete_chat">
                    <input type="hidden" name="id" value="<?= (int) $cm['id'] ?>">
                    <button class="btn keeper-bbs-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Reactions <span class="keeper-bbs-count"><?= count($reactions) ?></span></h2>

      <?php if (!empty($reactionSummary)): ?>
      <div class="keeper-bbs-reaction-summary">
        <?php foreach ($reactionSummary as $rs): ?>
        <span class="keeper-bbs-badge"><?= htmlspecialchars((string) $rs['emoji']) ?> <?= (int) $rs['c'] ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (empty($reactions)): ?>
        <p class="text-muted">No reactions.</p>
      <?php else: ?>
        <div class="keeper-table-scroll">
          <table class="keeper-table">
            <thead><tr><th>Emoji</th><th>User</th><th>Thread</th><th>Created</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($reactions as $r): ?>
              <tr>
                <td><?= htmlspecialchars((string) $r['emoji']) ?></td>
                <td><?= htmlspecialchars((string) $r['user_name']) ?></td>
                <td><?= ($r['thread_title'] !== null && $r['thread_title'] !== '') ? htmlspecialchars((string) $r['thread_title']) : '<span class="text-muted">—</span>' ?></td>
                <td><?= htmlspecialchars((string) $r['created_at']) ?></td>
                <td>
                  <form method="post" action="/keeper/bbs/chat.php" onsubmit="return confirm('Delete this reaction?');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete_reaction">
                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                    <button class="btn keeper-bbs-danger" type="submit">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

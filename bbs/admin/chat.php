<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/chat.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete_chat') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM chat_messages WHERE id = ?');
        $stmt->execute([$id]);
        adm_flash('success', 'Chat message deleted.');
        adm_redirect('/bbs/admin/chat.php');
    } elseif ($action === 'delete_reaction') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM reactions WHERE id = ?');
        $stmt->execute([$id]);
        adm_flash('success', 'Reaction deleted.');
        adm_redirect('/bbs/admin/chat.php');
    }

    adm_redirect('/bbs/admin/chat.php');
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
    'SELECT emoji, COUNT(*) AS c
     FROM reactions
     GROUP BY emoji
     ORDER BY c DESC'
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

$active = 'chat';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head"><h1>Chat</h1></div>

  <div class="admin-page-head"><h2>Recent Chat</h2></div>

  <?php if (empty($chatMessages)): ?>
    <p class="text-muted">No chat messages.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Thread</th>
            <th>Author</th>
            <th>Message</th>
            <th>Time</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chatMessages as $cm): ?>
            <tr>
              <td><?= adm_e($cm['thread_title']) ?></td>
              <td><?= adm_e($cm['author_name']) ?></td>
              <td><?= adm_e($cm['text']) ?></td>
              <td><?= adm_e($cm['timestamp']) ?></td>
              <td>
                <div class="action-group">
                  <form method="post" action="/bbs/admin/chat.php" class="action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_chat">
                    <input type="hidden" name="id" value="<?= adm_e((int)$cm['id']) ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this chat message?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="admin-page-head mt-6"><h2>Reactions</h2></div>

  <?php if (!empty($reactionSummary)): ?>
    <div class="flex gap-2 mb-4">
      <?php foreach ($reactionSummary as $rs): ?>
        <span class="badge"><?= adm_e($rs['emoji']) ?> <?= adm_e((int)$rs['c']) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (empty($reactions)): ?>
    <p class="text-muted">No reactions.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Emoji</th>
            <th>User</th>
            <th>Thread</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reactions as $r): ?>
            <tr>
              <td><?= adm_e($r['emoji']) ?></td>
              <td><?= adm_e($r['user_name']) ?></td>
              <td><?= ($r['thread_title'] !== null && $r['thread_title'] !== '') ? adm_e($r['thread_title']) : '—' ?></td>
              <td><?= adm_e($r['created_at']) ?></td>
              <td>
                <div class="action-group">
                  <form method="post" action="/bbs/admin/chat.php" class="action-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_reaction">
                    <input type="hidden" name="id" value="<?= adm_e((int)$r['id']) ?>">
                    <button class="btn btn-sm btn-danger" type="submit" data-confirm="Delete this reaction?">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

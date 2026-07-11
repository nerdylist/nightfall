<?php
require __DIR__ . '/partials/admin-bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/threads.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $tid = (int)($_POST['id'] ?? 0);

        $db = forum_db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM reactions WHERE post_id IN (SELECT id FROM posts WHERE thread_id = ?)');
            $stmt->execute([$tid]);

            $stmt = $db->prepare('DELETE FROM posts WHERE thread_id = ?');
            $stmt->execute([$tid]);

            $stmt = $db->prepare('DELETE FROM chat_messages WHERE thread_id = ?');
            $stmt->execute([$tid]);

            $stmt = $db->prepare('DELETE FROM threads WHERE id = ?');
            $stmt->execute([$tid]);

            $db->commit();
            adm_flash('success', 'Thread deleted.');
        } catch (Throwable $e) {
            $db->rollBack();
            adm_flash('error', 'Could not delete thread.');
        }
        adm_redirect('/bbs/admin/threads.php');
    }

    adm_redirect('/bbs/admin/threads.php');
}

$db = forum_db();
$threads = $db->query(
    "SELECT threads.*, c.name AS category_name,
            COALESCE(NULLIF(u.display_name, ''), u.username) AS author_name
     FROM threads
     JOIN categories c ON c.id = threads.category_id
     JOIN host.users u ON u.id = threads.author_id
     ORDER BY threads.id DESC"
)->fetchAll();

$active = 'threads';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head">
    <h1>Threads</h1>
  </div>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Title</th>
          <th>Category</th>
          <th>Author</th>
          <th>Replies</th>
          <th>Views</th>
          <th>Flags</th>
          <th>Created</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($threads as $t): ?>
        <tr>
          <td><?php echo adm_e($t['id']); ?></td>
          <td><?php echo adm_e($t['title']); ?></td>
          <td><?php echo adm_e($t['category_name']); ?></td>
          <td><?php echo adm_e($t['author_name']); ?></td>
          <td><?php echo adm_e($t['replies']); ?></td>
          <td><?php echo adm_e($t['views']); ?></td>
          <td>
            <?php if ((int)$t['pinned'] === 1): ?><span class="badge badge-pinned">Pinned</span><?php endif; ?>
            <?php if ((int)$t['locked'] === 1): ?><span class="badge">Locked</span><?php endif; ?>
            <?php if ((int)$t['hot'] === 1): ?><span class="badge badge-hot">Hot</span><?php endif; ?>
          </td>
          <td><?php echo adm_e($t['created_at']); ?></td>
          <td>
            <div class="action-group">
              <a class="btn btn-sm" href="/bbs/admin/thread-edit.php?id=<?php echo (int)$t['id']; ?>">Edit</a>
              <form method="post" action="/bbs/admin/threads.php" class="action-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$t['id']; ?>">
                <button class="btn btn-sm btn-danger" data-confirm="Delete this thread and all its posts/chat/reactions?">Delete</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

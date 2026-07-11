<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/threads.php');
    }

    $tid = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT * FROM threads WHERE id = ?');
    $stmt->execute([$tid]);
    $thread = $stmt->fetch();

    if (!$thread) {
        adm_flash('error', 'Thread not found.');
        adm_redirect('/bbs/admin/threads.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_thread') {
        $category_id = (int)($_POST['category_id'] ?? 0);

        $catStmt = $db->prepare('SELECT 1 FROM categories WHERE id = ?');
        $catStmt->execute([$category_id]);
        if (!$catStmt->fetchColumn()) {
            adm_flash('error', 'Category not found.');
            adm_redirect('/bbs/admin/thread-edit.php?id=' . $tid);
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            adm_flash('error', 'Title is required.');
            adm_redirect('/bbs/admin/thread-edit.php?id=' . $tid);
        }

        $excerpt = (string)($_POST['excerpt'] ?? '');
        $pinned = isset($_POST['pinned']) ? 1 : 0;
        $locked = isset($_POST['locked']) ? 1 : 0;
        $hot = isset($_POST['hot']) ? 1 : 0;

        $upd = $db->prepare('UPDATE threads SET title = ?, excerpt = ?, category_id = ?, pinned = ?, locked = ?, hot = ? WHERE id = ?');
        $upd->execute([$title, $excerpt, $category_id, $pinned, $locked, $hot, $tid]);

        if (isset($_POST['op_post_id'])) {
            $op_post_id = (int)$_POST['op_post_id'];
            if ($op_post_id > 0) {
                $op_body = (string)($_POST['op_body'] ?? '');
                $opUpd = $db->prepare('UPDATE posts SET body = ? WHERE id = ? AND thread_id = ?');
                $opUpd->execute([$op_body, $op_post_id, $tid]);
            }
        }

        adm_flash('success', 'Thread updated.');
        adm_redirect('/bbs/admin/thread-edit.php?id=' . $tid);
    } elseif ($action === 'delete_post') {
        $pid = (int)($_POST['pid'] ?? 0);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM reactions WHERE post_id = ?');
            $stmt->execute([$pid]);

            $stmt = $db->prepare('DELETE FROM posts WHERE id = ? AND thread_id = ?');
            $stmt->execute([$pid, $tid]);

            $db->commit();
            adm_flash('success', 'Post deleted.');
        } catch (Throwable $e) {
            $db->rollBack();
            adm_flash('error', 'Could not delete post.');
        }
        adm_redirect('/bbs/admin/thread-edit.php?id=' . $tid);
    }

    adm_redirect('/bbs/admin/thread-edit.php?id=' . $tid);
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare('SELECT * FROM threads WHERE id = ?');
$stmt->execute([$id]);
$thread = $stmt->fetch();

if (!$thread) {
    adm_flash('error', 'Thread not found.');
    adm_redirect('/bbs/admin/threads.php');
}

$categories = $db->query('SELECT id, name FROM categories ORDER BY sort_order, id')->fetchAll();

$opStmt = $db->prepare('SELECT * FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1');
$opStmt->execute([$id]);
$op = $opStmt->fetch();

$postsStmt = $db->prepare(
    "SELECT posts.*, COALESCE(NULLIF(u.display_name, ''), u.username) AS author_name
     FROM posts
     JOIN host.users u ON u.id = posts.author_id
     WHERE thread_id = ?
     ORDER BY posts.id ASC"
);
$postsStmt->execute([$id]);
$posts = $postsStmt->fetchAll();

$active = 'threads';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head"><h1>Edit Thread</h1></div>

  <form method="post" action="/bbs/admin/thread-edit.php?id=<?php echo (int)$thread['id']; ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_thread">
    <input type="hidden" name="id" value="<?php echo (int)$thread['id']; ?>">
    <?php if ($op): ?>
    <input type="hidden" name="op_post_id" value="<?php echo (int)$op['id']; ?>">
    <?php endif; ?>
    <div class="settings-group">
      <div class="form-grid">
        <div class="field">
          <label>Title</label>
          <input class="input" type="text" name="title" value="<?php echo adm_e($thread['title']); ?>" required>
        </div>
        <div class="field">
          <label>Category</label>
          <select class="input" name="category_id">
            <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$c['id'] === (int)$thread['category_id']) ? 'selected' : ''; ?>><?php echo adm_e($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field full-width">
          <label>Excerpt</label>
          <textarea class="textarea" name="excerpt"><?php echo adm_e($thread['excerpt']); ?></textarea>
        </div>
        <?php if ($op): ?>
        <div class="field full-width">
          <label>Original post body</label>
          <textarea class="textarea" name="op_body"><?php echo adm_e($op['body']); ?></textarea>
        </div>
        <?php endif; ?>
        <div class="field full-width">
          <label><input type="checkbox" name="pinned" <?php echo ((int)$thread['pinned'] === 1) ? 'checked' : ''; ?>> Pinned</label>
          <label><input type="checkbox" name="locked" <?php echo ((int)$thread['locked'] === 1) ? 'checked' : ''; ?>> Locked</label>
          <label><input type="checkbox" name="hot" <?php echo ((int)$thread['hot'] === 1) ? 'checked' : ''; ?>> Hot</label>
        </div>
      </div>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/bbs/admin/threads.php">Cancel</a>
        <button class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Author</th>
          <th>Body</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td><?php echo adm_e($p['id']); ?></td>
          <td><?php echo adm_e($p['author_name']); ?></td>
          <td><?php echo adm_e(mb_substr((string)$p['body'], 0, 120)); ?></td>
          <td>
            <div class="action-group">
              <form method="post" action="/bbs/admin/thread-edit.php?id=<?php echo (int)$thread['id']; ?>" class="action-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete_post">
                <input type="hidden" name="pid" value="<?php echo (int)$p['id']; ?>">
                <button class="btn btn-sm btn-danger" data-confirm="Delete this post?">Delete</button>
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

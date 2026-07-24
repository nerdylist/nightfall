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

/** Keeper > Forum > Edit Thread — edit thread + manage posts. Ported from bbs/admin/thread-edit.php. */
$keeperCsrf = keeper_bbs_csrf();
$db = keeper_bbs_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/bbs/threads.php');
        exit;
    }

    $tid = (int) ($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM threads WHERE id = ?');
    $stmt->execute([$tid]);
    $thread = $stmt->fetch();
    if (!$thread) {
        $_SESSION['keeper_flash'] = 'Thread not found.';
        header('Location: /keeper/bbs/threads.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save_thread') {
        $category_id = (int) ($_POST['category_id'] ?? 0);

        $catStmt = $db->prepare('SELECT 1 FROM categories WHERE id = ?');
        $catStmt->execute([$category_id]);
        if (!$catStmt->fetchColumn()) {
            $_SESSION['keeper_flash'] = 'Category not found.';
            header('Location: /keeper/bbs/thread-edit.php?id=' . $tid);
            exit;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $_SESSION['keeper_flash'] = 'Title is required.';
            header('Location: /keeper/bbs/thread-edit.php?id=' . $tid);
            exit;
        }

        $excerpt = (string) ($_POST['excerpt'] ?? '');
        $pinned = isset($_POST['pinned']) ? 1 : 0;
        $locked = isset($_POST['locked']) ? 1 : 0;
        $hot = isset($_POST['hot']) ? 1 : 0;

        $upd = $db->prepare('UPDATE threads SET title = ?, excerpt = ?, category_id = ?, pinned = ?, locked = ?, hot = ? WHERE id = ?');
        $upd->execute([$title, $excerpt, $category_id, $pinned, $locked, $hot, $tid]);

        if (isset($_POST['op_post_id'])) {
            $op_post_id = (int) $_POST['op_post_id'];
            if ($op_post_id > 0) {
                $op_body = (string) ($_POST['op_body'] ?? '');
                $opUpd = $db->prepare('UPDATE posts SET body = ? WHERE id = ? AND thread_id = ?');
                $opUpd->execute([$op_body, $op_post_id, $tid]);
            }
        }

        $_SESSION['keeper_flash'] = 'Thread updated.';
        header('Location: /keeper/bbs/thread-edit.php?id=' . $tid);
        exit;
    } elseif ($action === 'delete_post') {
        $pid = (int) ($_POST['pid'] ?? 0);

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM reactions WHERE post_id = ?');
            $stmt->execute([$pid]);

            $stmt = $db->prepare('DELETE FROM posts WHERE id = ? AND thread_id = ?');
            $stmt->execute([$pid, $tid]);

            $db->commit();
            $_SESSION['keeper_flash'] = 'Post deleted.';
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['keeper_flash'] = 'Could not delete post.';
        }
        header('Location: /keeper/bbs/thread-edit.php?id=' . $tid);
        exit;
    }

    header('Location: /keeper/bbs/thread-edit.php?id=' . $tid);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM threads WHERE id = ?');
$stmt->execute([$id]);
$thread = $stmt->fetch();
if (!$thread) {
    $_SESSION['keeper_flash'] = 'Thread not found.';
    header('Location: /keeper/bbs/threads.php');
    exit;
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

$pageTitle = 'Edit Thread — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Edit Thread</h1>

    <?php if ($flash): ?><p class="keeper-flash"><?= htmlspecialchars($flash) ?></p><?php endif; ?>

    <div class="card keeper-table-card">
      <form method="post" action="/keeper/bbs/thread-edit.php?id=<?= (int) $thread['id'] ?>" class="keeper-bbs-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="save_thread">
        <input type="hidden" name="id" value="<?= (int) $thread['id'] ?>">
        <?php if ($op): ?><input type="hidden" name="op_post_id" value="<?= (int) $op['id'] ?>"><?php endif; ?>

        <div class="keeper-bbs-grid">
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Title</span>
            <input class="field" type="text" name="title" value="<?= htmlspecialchars((string) $thread['title']) ?>" required>
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Category</span>
            <select class="field" name="category_id">
              <?php foreach ($categories as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= ((int) $c['id'] === (int) $thread['category_id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <label class="keeper-bbs-field keeper-bbs-field--full">
          <span class="keeper-bbs-label">Excerpt</span>
          <textarea class="field" name="excerpt" rows="2"><?= htmlspecialchars((string) $thread['excerpt']) ?></textarea>
        </label>
        <?php if ($op): ?>
        <label class="keeper-bbs-field keeper-bbs-field--full">
          <span class="keeper-bbs-label">Original post body</span>
          <textarea class="field" name="op_body" rows="4"><?= htmlspecialchars((string) $op['body']) ?></textarea>
        </label>
        <?php endif; ?>

        <div class="keeper-bbs-checks">
          <label><input type="checkbox" name="pinned" <?= ((int) $thread['pinned'] === 1) ? 'checked' : '' ?>> Pinned</label>
          <label><input type="checkbox" name="locked" <?= ((int) $thread['locked'] === 1) ? 'checked' : '' ?>> Locked</label>
          <label><input type="checkbox" name="hot" <?= ((int) $thread['hot'] === 1) ? 'checked' : '' ?>> Hot</label>
        </div>

        <div class="keeper-bbs-actions">
          <a class="btn btn-ghost" href="/keeper/bbs/threads.php">Cancel</a>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Posts <span class="keeper-bbs-count"><?= count($posts) ?></span></h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead><tr><th>ID</th><th>Author</th><th>Body</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
            <tr>
              <td><?= (int) $p['id'] ?></td>
              <td><?= htmlspecialchars((string) $p['author_name']) ?></td>
              <td class="keeper-bbs-desc"><?= htmlspecialchars(mb_substr((string) $p['body'], 0, 120)) ?></td>
              <td>
                <form method="post" action="/keeper/bbs/thread-edit.php?id=<?= (int) $thread['id'] ?>" onsubmit="return confirm('Delete this post?');">
                  <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                  <input type="hidden" name="action" value="delete_post">
                  <input type="hidden" name="pid" value="<?= (int) $p['id'] ?>">
                  <button class="btn keeper-bbs-danger" type="submit">Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($posts)): ?><tr><td colspan="4" class="text-muted">No posts.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

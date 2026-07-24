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

/** Keeper > Forum > Threads — list + delete (cascade). Ported from bbs/admin/threads.php. */
$keeperCsrf = keeper_bbs_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/bbs/threads.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $tid = (int) ($_POST['id'] ?? 0);

        $db = keeper_bbs_db();
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
            $_SESSION['keeper_flash'] = 'Thread deleted.';
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['keeper_flash'] = 'Could not delete thread.';
        }
        header('Location: /keeper/bbs/threads.php');
        exit;
    }

    header('Location: /keeper/bbs/threads.php');
    exit;
}

$db = keeper_bbs_db();
$threads = $db->query(
    "SELECT threads.*, c.name AS category_name,
            COALESCE(NULLIF(u.display_name, ''), u.username) AS author_name
     FROM threads
     JOIN categories c ON c.id = threads.category_id
     JOIN host.users u ON u.id = threads.author_id
     ORDER BY threads.id DESC"
)->fetchAll();

$pageTitle = 'Forum Threads — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Forum Threads</h1>

    <?php if ($flash): ?><p class="keeper-flash"><?= htmlspecialchars($flash) ?></p><?php endif; ?>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Threads <span class="keeper-bbs-count"><?= count($threads) ?></span></h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr><th>ID</th><th>Title</th><th>Category</th><th>Author</th><th>Replies</th><th>Views</th><th>Flags</th><th>Created</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($threads as $t): ?>
            <tr>
              <td class="keeper-cell-num"><?= (int) $t['id'] ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--lg" title="<?= htmlspecialchars((string) $t['title']) ?>"><?= htmlspecialchars((string) $t['title']) ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars((string) $t['category_name']) ?>"><?= htmlspecialchars((string) $t['category_name']) ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars((string) $t['author_name']) ?>"><?= htmlspecialchars((string) $t['author_name']) ?></td>
              <td class="keeper-cell-num"><?= (int) $t['replies'] ?></td>
              <td class="keeper-cell-num"><?= (int) $t['views'] ?></td>
              <td class="keeper-bbs-flags">
                <?php if ((int) $t['pinned'] === 1): ?><span class="keeper-bbs-badge">Pinned</span><?php endif; ?>
                <?php if ((int) $t['locked'] === 1): ?><span class="keeper-bbs-badge">Locked</span><?php endif; ?>
                <?php if ((int) $t['hot'] === 1): ?><span class="keeper-bbs-badge">Hot</span><?php endif; ?>
              </td>
              <td class="keeper-cell-nowrap"><?= htmlspecialchars(substr((string) $t['created_at'], 0, 10)) ?></td>
              <td>
                <div class="keeper-row-actions">
                  <a class="keeper-icon-btn" href="/keeper/bbs/thread-edit.php?id=<?= (int) $t['id'] ?>" title="Edit thread" aria-label="Edit">
                    <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/pen-to-square.svg" alt="">
                  </a>
                  <form method="post" action="/keeper/bbs/threads.php" onsubmit="return confirm('Delete this thread and all its posts/chat/reactions?');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button class="keeper-icon-btn keeper-icon-btn--danger" type="submit" title="Delete thread" aria-label="Delete">
                      <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash.svg" alt="">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($threads)): ?><tr><td colspan="9" class="text-muted">No threads.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

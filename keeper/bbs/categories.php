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

/** Keeper > Forum > Categories — list, create, delete. Ported from bbs/admin/categories.php. */
$keeperCsrf = keeper_bbs_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/bbs/categories.php');
        exit;
    }

    $db = keeper_bbs_db();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $_SESSION['keeper_flash'] = 'Name is required.';
            header('Location: /keeper/bbs/categories.php');
            exit;
        }
        $description = (string) ($_POST['description'] ?? '');
        $icon = (string) ($_POST['icon'] ?? '');
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $color = (string) ($_POST['color'] ?? '');
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            $color = '#7a64f5';
        }
        $featured = isset($_POST['featured']) ? 1 : 0;

        $stmt = $db->prepare('INSERT INTO categories (name, description, icon, color, sort_order, featured, thread_count, post_count, last_activity, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)');
        $stmt->execute([$name, $description, $icon, $color, $sort_order, $featured, 'just now', gmdate('c')]);

        $_SESSION['keeper_flash'] = 'Category created.';
        header('Location: /keeper/bbs/categories.php');
        exit;
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) FROM threads WHERE category_id = ?');
        $stmt->execute([$id]);
        $threadCount = (int) $stmt->fetchColumn();

        if ($threadCount > 0) {
            $_SESSION['keeper_flash'] = 'Category has threads and cannot be deleted.';
            header('Location: /keeper/bbs/categories.php');
            exit;
        }

        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        $_SESSION['keeper_flash'] = 'Category deleted.';
        header('Location: /keeper/bbs/categories.php');
        exit;
    }

    header('Location: /keeper/bbs/categories.php');
    exit;
}

$db = keeper_bbs_db();
$categories = $db->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();

$pageTitle = 'Forum Categories — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Forum Categories</h1>

    <?php if ($flash): ?><p class="keeper-flash"><?= htmlspecialchars($flash) ?></p><?php endif; ?>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Add Category</h2>
      <form method="post" action="/keeper/bbs/categories.php" class="keeper-bbs-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="keeper-bbs-grid">
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Name</span>
            <input class="field" type="text" name="name" placeholder="General Chit-Chat" required>
          </label>
          <label class="keeper-bbs-field keeper-bbs-field--wide">
            <span class="keeper-bbs-label">Description</span>
            <input class="field" type="text" name="description" placeholder="Short description">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Sort order</span>
            <input class="field" type="number" name="sort_order" value="0">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Badge (text/emoji/URL)</span>
            <input class="field" type="text" name="icon" placeholder="💀 or a URL">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Color</span>
            <input class="field keeper-bbs-color" type="color" name="color" value="#7a64f5">
          </label>
          <label class="keeper-bbs-field keeper-bbs-field--check">
            <input type="checkbox" name="featured" value="1">
            <span class="keeper-bbs-label">Featured (show on home)</span>
          </label>
        </div>
        <div class="keeper-bbs-actions">
          <button type="submit" class="btn btn-primary">Create Category</button>
        </div>
      </form>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Categories <span class="keeper-bbs-count"><?= count($categories) ?></span></h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr><th>ID</th><th></th><th>Name</th><th>Description</th><th>Sort</th><th>Featured</th><th>Threads</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr>
              <td><?= (int) $cat['id'] ?></td>
              <td><span class="keeper-bbs-swatch" style="background: <?= htmlspecialchars((string) ($cat['color'] ?: '#7a64f5')) ?>;"></span></td>
              <td><?= htmlspecialchars((string) $cat['name']) ?></td>
              <td class="keeper-bbs-desc"><?= htmlspecialchars((string) $cat['description']) ?></td>
              <td><?= (int) $cat['sort_order'] ?></td>
              <td><?= !empty($cat['featured']) ? '<span title="Featured">&#9733;</span>' : '<span class="text-muted">—</span>' ?></td>
              <td><?= (int) $cat['thread_count'] ?></td>
              <td>
                <div class="keeper-action-group">
                  <a class="btn" href="/keeper/bbs/category-edit.php?id=<?= (int) $cat['id'] ?>">Edit</a>
                  <form method="post" action="/keeper/bbs/categories.php" onsubmit="return confirm('Delete this category?');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                    <button class="btn keeper-bbs-danger" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?><tr><td colspan="8" class="text-muted">No categories yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

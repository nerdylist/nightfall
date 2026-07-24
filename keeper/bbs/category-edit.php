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

/** Keeper > Forum > Edit Category. Ported from bbs/admin/category-edit.php. */
$keeperCsrf = keeper_bbs_csrf();
$db = keeper_bbs_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/bbs/categories.php');
        exit;
    }

    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if (!$cat) {
        $_SESSION['keeper_flash'] = 'Category not found.';
        header('Location: /keeper/bbs/categories.php');
        exit;
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        $_SESSION['keeper_flash'] = 'Name is required.';
        header('Location: /keeper/bbs/category-edit.php?id=' . $id);
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

    $stmt = $db->prepare('UPDATE categories SET name = ?, description = ?, icon = ?, color = ?, sort_order = ?, featured = ? WHERE id = ?');
    $stmt->execute([$name, $description, $icon, $color, $sort_order, $featured, $id]);

    $_SESSION['keeper_flash'] = 'Category updated.';
    header('Location: /keeper/bbs/categories.php');
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$cat = $stmt->fetch();
if (!$cat) {
    $_SESSION['keeper_flash'] = 'Category not found.';
    header('Location: /keeper/bbs/categories.php');
    exit;
}

$catColor = (string) ($cat['color'] ?? '');
if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $catColor)) {
    $catColor = '#7a64f5';
}

$pageTitle = 'Edit Category — Keeper';
$pageCss = ['/css/keeper-bbs.css'];
include __DIR__ . '/../../partials/keeper-header.php';
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Edit Category</h1>

    <div class="card keeper-table-card">
      <form method="post" action="/keeper/bbs/category-edit.php?id=<?= (int) $cat['id'] ?>" class="keeper-bbs-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
        <div class="keeper-bbs-grid">
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Name</span>
            <input class="field" type="text" name="name" value="<?= htmlspecialchars((string) $cat['name']) ?>" required>
          </label>
          <label class="keeper-bbs-field keeper-bbs-field--wide">
            <span class="keeper-bbs-label">Description</span>
            <input class="field" type="text" name="description" value="<?= htmlspecialchars((string) $cat['description']) ?>">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Sort order</span>
            <input class="field" type="number" name="sort_order" value="<?= (int) $cat['sort_order'] ?>">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Badge (text/emoji/URL)</span>
            <input class="field" type="text" name="icon" value="<?= htmlspecialchars((string) $cat['icon']) ?>">
          </label>
          <label class="keeper-bbs-field">
            <span class="keeper-bbs-label">Color</span>
            <input class="field keeper-bbs-color" type="color" name="color" value="<?= htmlspecialchars($catColor) ?>">
          </label>
          <label class="keeper-bbs-field keeper-bbs-field--check">
            <input type="checkbox" name="featured" value="1"<?= !empty($cat['featured']) ? ' checked' : '' ?>>
            <span class="keeper-bbs-label">Featured (show on home)</span>
          </label>
        </div>
        <div class="keeper-bbs-actions">
          <a class="btn btn-ghost" href="/keeper/bbs/categories.php">Cancel</a>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../../partials/keeper-footer.php'; ?>

<?php
require __DIR__ . '/partials/admin-bootstrap.php';

$db = forum_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/categories.php');
    }

    $id = (int)($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$id]);
    $cat = $stmt->fetch();

    if (!$cat) {
        adm_flash('error', 'Category not found.');
        adm_redirect('/bbs/admin/categories.php');
    }

    $name = trim($_POST['name'] ?? '');
    if ($name === '') {
        adm_flash('error', 'Name is required.');
        adm_redirect('/bbs/admin/category-edit.php?id=' . $id);
    }
    $description = (string)($_POST['description'] ?? '');
    $icon = (string)($_POST['icon'] ?? '');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    // Validate hex color; fall back to the accent default if invalid.
    $color = (string)($_POST['color'] ?? '');
    if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
        $color = '#7a64f5';
    }
    $featured = isset($_POST['featured']) ? 1 : 0;

    $stmt = $db->prepare('UPDATE categories SET name = ?, description = ?, icon = ?, color = ?, sort_order = ?, featured = ? WHERE id = ?');
    $stmt->execute([$name, $description, $icon, $color, $sort_order, $featured, $id]);

    adm_flash('success', 'Category updated.');
    adm_redirect('/bbs/admin/categories.php');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
$stmt->execute([$id]);
$cat = $stmt->fetch();

if (!$cat) {
    adm_flash('error', 'Category not found.');
    adm_redirect('/bbs/admin/categories.php');
}

$active = 'categories';
$EXTRA_CSS = ['admin/css/admin.css'];
$BASE = '/bbs/';
include __DIR__ . '/../partials/head.php';
include __DIR__ . '/../partials/header.php';
include __DIR__ . '/partials/admin-nav.php';
?>
<main class="container admin-main">
  <?php include __DIR__ . '/partials/admin-flash.php'; ?>

  <div class="admin-page-head">
    <h1>Edit Category</h1>
  </div>

  <form method="post" action="/bbs/admin/category-edit.php?id=<?php echo (int)$cat['id']; ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
    <div class="settings-group">
      <div class="category-form-grid">
        <div class="field">
          <input class="input" type="text" name="name" placeholder="Name" value="<?php echo adm_e($cat['name']); ?>" required>
        </div>
        <div class="field span-2">
          <input class="input" type="text" name="description" placeholder="Description" value="<?php echo adm_e($cat['description']); ?>">
        </div>
        <div class="field">
          <input class="input" type="number" name="sort_order" placeholder="Sort order" value="<?php echo adm_e($cat['sort_order']); ?>">
        </div>
        <div class="field">
          <?php /* name stays "icon" / DB column `icon`, but it now holds a badge value (text/emoji/URL). */ ?>
          <input class="input" type="text" name="icon" placeholder="Badge (text, emoji, or image URL)" value="<?php echo adm_e($cat['icon']); ?>">
        </div>
        <div class="field">
          <?php
          // Validate the stored color so the value attr is always a clean hex.
          $catColor = (string)($cat['color'] ?? '');
          if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $catColor)) {
              $catColor = '#7a64f5';
          }
          ?>
          <input class="input input-color" type="color" name="color" value="<?php echo adm_e($catColor); ?>">
        </div>
      </div>
      <label class="checkbox-row">
        <input type="checkbox" name="featured" value="1"<?php echo !empty($cat['featured']) ? ' checked' : ''; ?>>
        <span>Featured &mdash; show on the home page</span>
      </label>
      <div class="settings-actions">
        <a class="btn btn-ghost" href="/bbs/admin/categories.php">Cancel</a>
        <button class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>
</main>
<?php include __DIR__ . '/partials/admin-footer.php';

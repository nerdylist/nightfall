<?php
require __DIR__ . '/partials/admin-bootstrap.php';
require_once __DIR__ . '/../partials/category-badge.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        adm_flash('error', 'Invalid request.');
        adm_redirect('/bbs/admin/categories.php');
    }

    $db = forum_db();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            adm_flash('error', 'Name is required.');
            adm_redirect('/bbs/admin/categories.php');
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

        $stmt = $db->prepare('INSERT INTO categories (name, description, icon, color, sort_order, featured, thread_count, post_count, last_activity, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?)');
        $stmt->execute([$name, $description, $icon, $color, $sort_order, $featured, 'just now', gmdate('c')]);

        adm_flash('success', 'Category created.');
        adm_redirect('/bbs/admin/categories.php');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = $db->prepare('SELECT COUNT(*) FROM threads WHERE category_id = ?');
        $stmt->execute([$id]);
        $threadCount = (int)$stmt->fetchColumn();

        if ($threadCount > 0) {
            adm_flash('error', 'Category has threads and cannot be deleted.');
            adm_redirect('/bbs/admin/categories.php');
        }

        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);

        adm_flash('success', 'Category deleted.');
        adm_redirect('/bbs/admin/categories.php');
    }

    adm_redirect('/bbs/admin/categories.php');
}

$db = forum_db();
$categories = $db->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();

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
    <h1>Categories</h1>
  </div>

  <form method="post" action="/bbs/admin/categories.php">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create">
    <div class="settings-group">
      <p class="group-desc">Create a new category.</p>
      <div class="category-form-grid">
        <div class="field">
          <input class="input" type="text" name="name" placeholder="Name" required>
        </div>
        <div class="field span-2">
          <input class="input" type="text" name="description" placeholder="Description">
        </div>
        <div class="field">
          <input class="input" type="number" name="sort_order" placeholder="Sort order">
        </div>
        <div class="field">
          <?php /* name stays "icon" / DB column `icon`, but it now holds a badge value (text/emoji/URL). */ ?>
          <input class="input" type="text" name="icon" placeholder="Badge (text, emoji, or image URL)">
        </div>
        <div class="field">
          <input class="input input-color" type="color" name="color" value="#7a64f5">
        </div>
      </div>
      <label class="checkbox-row">
        <input type="checkbox" name="featured" value="1">
        <span>Featured &mdash; show on the home page</span>
      </label>
      <div class="settings-actions">
        <button class="btn btn-primary">Create category</button>
      </div>
    </div>
  </form>

  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Badge</th>
          <th>Name</th>
          <th>Description</th>
          <th>Sort</th>
          <th>Featured</th>
          <th>Threads</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr>
          <td><?php echo adm_e($cat['id']); ?></td>
          <td><span class="cat-badge<?php echo forum_category_badge_is_image($cat) ? ' is-image' : ''; ?>" style="--cat-color: <?php echo forum_category_color($cat); ?>;"><?php echo forum_category_badge($cat); ?></span></td>
          <td><?php echo adm_e($cat['name']); ?></td>
          <td><?php echo adm_e($cat['description']); ?></td>
          <td><?php echo adm_e($cat['sort_order']); ?></td>
          <td><?php echo !empty($cat['featured']) ? '<span class="featured-star" title="Featured">&#9733;</span>' : ''; ?></td>
          <td><?php echo adm_e($cat['thread_count']); ?></td>
          <td>
            <div class="action-group">
              <a class="btn btn-sm" href="/bbs/admin/category-edit.php?id=<?php echo (int)$cat['id']; ?>">Edit</a>
              <form method="post" action="/bbs/admin/categories.php" class="action-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                <button class="btn btn-sm btn-danger" data-confirm="Delete this category?">Delete</button>
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

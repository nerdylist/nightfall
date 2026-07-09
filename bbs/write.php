<?php
require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
require_once __DIR__ . '/lib/bbcode.php';
require_once __DIR__ . '/data/db.php';
$data = require __DIR__ . '/data/live.php';

require_login();

// Resolve the requested category.
$categories = $data['categories'] ?? [];
$requestedId = isset($_GET['category']) ? (int) $_GET['category'] : (int) ($_POST['category_id'] ?? 0);

$selectedCategory = null;
$selectedCategoryId = 0;
foreach ($categories as $cat) {
    if ((int) $cat['id'] === $requestedId) {
        $selectedCategory = $cat;
        $selectedCategoryId = (int) $cat['id'];
        break;
    }
}

// On a fresh GET with no valid category, send the user home.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $selectedCategory === null) {
    header('Location: index.php');
    exit;
}

$errors = [];
$old = ['title' => '', 'body' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['title'] = trim((string) ($_POST['title'] ?? ''));
    $old['body'] = (string) ($_POST['body'] ?? '');

    // Validate the posted category against the known categories.
    $postedId = (int) ($_POST['category_id'] ?? 0);
    $categoryId = 0;
    foreach ($categories as $cat) {
        if ((int) $cat['id'] === $postedId) {
            $categoryId = (int) $cat['id'];
            break;
        }
    }
    if ($categoryId !== 0) {
        $selectedCategoryId = $categoryId;
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $categoryId) {
                $selectedCategory = $cat;
                break;
            }
        }
    } else {
        $errors[] = 'Please choose a valid category.';
        // Fall back to the first category so the page still renders a real
        // name and a working Cancel link instead of an empty/id=0 state.
        if ($selectedCategory === null && !empty($categories)) {
            $selectedCategory = $categories[0];
            $selectedCategoryId = (int) $categories[0]['id'];
        }
    }

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if ($old['title'] === '') {
        $errors[] = 'Please enter a thread subject.';
    }
    if (trim($old['body']) === '') {
        $errors[] = 'Please write some content.';
    }

    if (empty($errors)) {
        $me = auth_current_user();
        $uid = (int) $me['id'];
        $excerpt = bbcode_excerpt($old['body']);
        try {
            $id = create_thread($categoryId, $uid, $old['title'], $old['body'], $excerpt);
            header('Location: thread.php?id=' . $id);
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Something went wrong creating your thread. Please try again.';
        }
    }
}

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>
<main class="container">
  <div class="write-page">
    <div class="write-card">
      <h1>New Thread</h1>
      <p class="write-subtitle">Posting in <span><?= htmlspecialchars($selectedCategory['name'] ?? '') ?></span></p>

      <?php if (!empty($errors)): ?>
        <div class="auth-error">
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" action="write.php">
        <?= csrf_field() ?>
        <input type="hidden" name="category_id" value="<?= (int) $selectedCategoryId ?>">
        <div class="write-field">
          <input type="text" name="title" maxlength="150" placeholder="Thread subject" required value="<?= htmlspecialchars($old['title']) ?>">
        </div>

        <div data-bbcode-editor data-csrf="<?= htmlspecialchars(csrf_token()) ?>">
          <textarea name="body" placeholder="Write your post&hellip; (BBCode supported)" data-bbcode-textarea><?= htmlspecialchars($old['body']) ?></textarea>
          <div class="editor-status" data-bbcode-status aria-live="polite"></div>
        </div>

        <div class="write-actions">
          <button type="submit" class="btn btn-primary">Post Thread</button>
          <a class="btn btn-ghost" href="category.php?id=<?= $selectedCategoryId ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

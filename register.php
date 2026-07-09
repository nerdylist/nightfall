<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

$pageTitle = 'Register — The Dead Last';
$pageCss = ['/css/register.css'];
$pageJs = ['/js/register.js'];

$formErrors = [];
$formSuccess = null;
$oldEmail = '';
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    $oldUsername = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    $formErrors = grave_validate_registration($oldEmail, $oldUsername, $password);

    if ($passwordConfirm !== $password) {
        $formErrors['passwordConfirm'] = 'Passwords do not match.';
    }

    if (empty($formErrors)) {
        $pdo = grave_db();
        try {
            grave_create_user($pdo, $oldEmail, $oldUsername, $password);
            $formSuccess = 'Survivor created. You may now log in.';
            $oldEmail = '';
            $oldUsername = '';
        } catch (GraveDuplicateError $e) {
            $formErrors['email'] = $e->getMessage();
        }
    }
}

include __DIR__ . '/partials/header.php';
?>

<main class="auth">
  <div class="container auth__container">
    <div class="card auth__card">
      <h1 class="auth__heading">Create Survivor</h1>
      <p class="text-muted auth__sub">Every survivor's story starts here. Choose wisely.</p>

      <?php if ($formSuccess): ?>
        <p class="text-muted auth__sub"><?= htmlspecialchars($formSuccess) ?></p>
      <?php endif; ?>

      <form id="registerForm" class="auth__form" action="/register.php" method="post" novalidate>
        <div class="form-row">
          <div>
            <input class="field" type="email" name="email" id="email" placeholder="Email" autocomplete="email" value="<?= htmlspecialchars($oldEmail) ?>">
            <p class="error-text" id="emailError"><?= htmlspecialchars($formErrors['email'] ?? '') ?></p>
          </div>
          <div>
            <input class="field" type="text" name="username" id="username" placeholder="Username" autocomplete="username" value="<?= htmlspecialchars($oldUsername) ?>">
            <p class="error-text" id="usernameError"><?= htmlspecialchars($formErrors['username'] ?? '') ?></p>
          </div>
        </div>

        <div class="form-row">
          <div>
            <input class="field" type="password" name="password" id="password" placeholder="Password" autocomplete="new-password">
            <p class="error-text" id="passwordError"><?= htmlspecialchars($formErrors['password'] ?? '') ?></p>
          </div>
          <div>
            <input class="field" type="password" name="password_confirm" id="passwordConfirm" placeholder="Confirm Password" autocomplete="new-password">
            <p class="error-text" id="passwordConfirmError"><?= htmlspecialchars($formErrors['passwordConfirm'] ?? '') ?></p>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Register</button>
      </form>

      <p class="auth__switch text-muted">
        Already have a survivor? <a href="/login.php">Login</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

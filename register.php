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

// SSO: pass a safe local ?next through to the login link so the user returns
// to where they came from (e.g. /bbs/...) after registering + logging in.
$grave_safe_next = static function ($next): string {
    $next = (string) $next;
    if ($next === '' || $next[0] !== '/') return '';
    if (strpos($next, '//') === 0) return '';
    if (strpos($next, "\\") !== false) return '';
    if (strpos($next, ':') !== false) return '';
    return $next;
};
$next = $grave_safe_next($_POST['next'] ?? $_GET['next'] ?? '');
$loginHref = '/login.php' . ($next !== '' ? '?next=' . urlencode($next) : '');
$registerAction = '/register.php' . ($next !== '' ? '?next=' . urlencode($next) : '');

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

      <form id="registerForm" class="auth__form" action="<?= htmlspecialchars($registerAction) ?>" method="post" novalidate>
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
        Already have a survivor? <a href="<?= htmlspecialchars($loginHref) ?>">Login</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

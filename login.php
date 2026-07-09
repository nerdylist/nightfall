<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';

$pageTitle = 'Login — The Dead Last';
$pageCss = ['/css/login.css'];
$pageJs = ['/js/login.js'];

$formErrors = [];
$oldIdentifier = '';

// SSO: honor a safe local ?next redirect target (e.g. back to /bbs/...).
// Only root-relative paths are allowed (no scheme/host/backslash) to prevent
// open redirects. Falls back to /index.php.
$grave_safe_next = static function ($next): string {
    $next = (string) $next;
    if ($next === '' || $next[0] !== '/') return '/index.php';
    if (strpos($next, '//') === 0) return '/index.php';
    if (strpos($next, "\\") !== false) return '/index.php';
    if (strpos($next, ':') !== false) return '/index.php';
    return $next;
};
$next = $grave_safe_next($_POST['next'] ?? $_GET['next'] ?? '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldIdentifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($oldIdentifier === '') {
        $formErrors['identifier'] = 'Username or email is required.';
    }
    if ($password === '') {
        $formErrors['password'] = 'Password is required.';
    }

    if (empty($formErrors)) {
        $pdo = grave_db();
        $user = grave_verify_login($pdo, $oldIdentifier, $password);

        if (!$user) {
            $formErrors['password'] = 'Invalid username/email or password.';
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: ' . $next);
            exit;
        }
    }
}

include __DIR__ . '/partials/header.php';
?>

<main class="auth">
  <div class="container auth__container">
    <div class="card auth__card">
      <h1 class="auth__heading">Login</h1>
      <p class="text-muted auth__sub">Return to the world. It hasn't forgotten you.</p>

      <form id="loginForm" class="auth__form" action="/login.php" method="post" novalidate>
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <div>
          <input class="field" type="text" name="identifier" id="identifier" placeholder="Username or Email" autocomplete="username" value="<?= htmlspecialchars($oldIdentifier) ?>">
          <p class="error-text" id="identifierError"><?= htmlspecialchars($formErrors['identifier'] ?? '') ?></p>
        </div>
        <div>
          <input class="field" type="password" name="password" id="password" placeholder="Password" autocomplete="current-password">
          <p class="error-text" id="passwordError"><?= htmlspecialchars($formErrors['password'] ?? '') ?></p>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Login</button>
      </form>

      <p class="auth__switch text-muted">
        Need a survivor? <a href="/register.php">Register</a>
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

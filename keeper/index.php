<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Keeper Login — The Dead Last Admin';
$pageCss = [];
$keeperLoginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedUser = trim((string) ($_POST['keeper_user'] ?? ''));
    $submittedPass = (string) ($_POST['keeper_pass'] ?? '');

    $adminUser = env('KEEPER_ADMIN_USER', '');
    $adminHash = env('KEEPER_ADMIN_PASS_HASH', '');

    if ($submittedUser !== '' && $submittedPass !== ''
        && hash_equals($adminUser, $submittedUser)
        && password_verify($submittedPass, $adminHash)
    ) {
        $_SESSION['keeper_admin'] = true;
        header('Location: /keeper/dashboard.php');
        exit;
    }

    $keeperLoginError = 'Invalid admin username or password.';
}

include __DIR__ . '/../partials/keeper-header.php';
?>

<main class="keeper-main keeper-main--centered">
  <div class="container">
    <div class="card keeper-login-card">
      <h1 class="keeper-login-card__heading">Keeper Access</h1>
      <p class="text-muted keeper-login-card__sub">Restricted area. Admin credentials required.</p>

      <form id="keeperLoginForm" class="keeper-login-card__form" action="/keeper/index.php" method="post" novalidate>
        <input class="field" type="text" name="keeper_user" id="keeperUser" placeholder="Admin Username" autocomplete="username">
        <input class="field" type="password" name="keeper_pass" id="keeperPass" placeholder="Admin Password" autocomplete="current-password">
        <p class="error-text" id="keeperLoginError"><?= htmlspecialchars($keeperLoginError) ?></p>
        <button type="submit" class="btn btn-primary btn-block">Enter Keeper</button>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

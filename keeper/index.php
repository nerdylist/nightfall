<?php
require_once __DIR__ . '/../config.php';
$pageTitle = 'Keeper Login — Grave Rising Admin';
$pageCss = [];
include __DIR__ . '/../partials/keeper-header.php';
?>

<main class="keeper-main keeper-main--centered">
  <div class="container">
    <div class="card keeper-login-card">
      <h1 class="keeper-login-card__heading">Keeper Access</h1>
      <p class="text-muted keeper-login-card__sub">Restricted area. Admin credentials required.</p>

      <!--
        BACKEND WIRING GOES HERE:
        - Compare submitted username/password against KEEPER_ADMIN_USER
          and KEEPER_ADMIN_PASS_HASH from .env (password_verify()).
        - On success, start a session and gate all /keeper pages on it.
        - For this prototype, the dashboard below renders directly as the
          "logged in" state with no real gate.
      -->
      <form id="keeperLoginForm" class="keeper-login-card__form" action="#" method="post" novalidate>
        <input class="field" type="text" name="keeper_user" id="keeperUser" placeholder="Admin Username" autocomplete="username">
        <input class="field" type="password" name="keeper_pass" id="keeperPass" placeholder="Admin Password" autocomplete="current-password">
        <p class="error-text" id="keeperLoginError"></p>
        <button type="submit" class="btn btn-primary btn-block">Enter Keeper</button>
      </form>

      <p class="text-muted keeper-login-card__note">
        Prototype note: this gate is not enforced yet &mdash; see
        <a href="/keeper/dashboard.php">dashboard.php</a> directly.
      </p>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

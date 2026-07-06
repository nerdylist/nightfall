<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Login — Grave Rising';
$pageCss = ['/css/login.css'];
$pageJs = ['/js/login.js'];
include __DIR__ . '/partials/header.php';
?>

<main class="auth">
  <div class="container auth__container">
    <div class="card auth__card">
      <h1 class="auth__heading">Login</h1>
      <p class="text-muted auth__sub">Return to the world. It hasn't forgotten you.</p>

      <!-- BACKEND WIRING GOES HERE — POST to login handler, verify against SQLite users table, start session -->
      <form id="loginForm" class="auth__form" action="#" method="post" novalidate>
        <div>
          <input class="field" type="text" name="identifier" id="identifier" placeholder="Username or Email" autocomplete="username">
          <p class="error-text" id="identifierError"></p>
        </div>
        <div>
          <input class="field" type="password" name="password" id="password" placeholder="Password" autocomplete="current-password">
          <p class="error-text" id="passwordError"></p>
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

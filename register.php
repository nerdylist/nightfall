<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Register — Grave Rising';
$pageCss = ['/css/register.css'];
$pageJs = ['/js/register.js'];
include __DIR__ . '/partials/header.php';
?>

<main class="auth">
  <div class="container auth__container">
    <div class="card auth__card">
      <h1 class="auth__heading">Create Survivor</h1>
      <p class="text-muted auth__sub">Every survivor's story starts here. Choose wisely.</p>

      <!-- BACKEND WIRING GOES HERE — POST to register handler, insert into SQLite users table -->
      <form id="registerForm" class="auth__form" action="#" method="post" novalidate>
        <div class="form-row">
          <div>
            <input class="field" type="email" name="email" id="email" placeholder="Email" autocomplete="email">
            <p class="error-text" id="emailError"></p>
          </div>
          <div>
            <input class="field" type="text" name="username" id="username" placeholder="Username" autocomplete="username">
            <p class="error-text" id="usernameError"></p>
          </div>
        </div>

        <div class="form-row">
          <div>
            <input class="field" type="password" name="password" id="password" placeholder="Password" autocomplete="new-password">
            <p class="error-text" id="passwordError"></p>
          </div>
          <div>
            <input class="field" type="password" name="password_confirm" id="passwordConfirm" placeholder="Confirm Password" autocomplete="new-password">
            <p class="error-text" id="passwordConfirmError"></p>
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

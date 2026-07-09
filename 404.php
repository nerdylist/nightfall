<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Not Found — The Dead Last';
$pageCss = [];
http_response_code(404); // ensure 404 even though router already set it
include __DIR__ . '/partials/header.php';
?>
<main class="auth">
  <div class="container auth__container">
    <div class="card auth__card" style="text-align:center">
      <h1 class="auth__heading">404</h1>
      <p class="text-muted auth__sub">This place is dead. The page you sought is gone.</p>
      <p><a class="btn btn-primary" href="/">Back to safety</a></p>
    </div>
  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

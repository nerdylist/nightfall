// GRAVE RISING — Keeper admin shared JS

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('keeperLoginForm');
  if (!form) return;

  var keeperUser = document.getElementById('keeperUser');
  var keeperPass = document.getElementById('keeperPass');
  var keeperLoginError = document.getElementById('keeperLoginError');

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    if (!keeperUser.value.trim() || !keeperPass.value) {
      graveSetFieldError(keeperLoginError, 'Username and password are required.');
      return;
    }

    graveSetFieldError(keeperLoginError, '');

    // BACKEND WIRING GOES HERE — verify against KEEPER_ADMIN_USER /
    // KEEPER_ADMIN_PASS_HASH (from .env) via password_verify(), then
    // start a real session and redirect to /keeper/dashboard.php.
    console.log('Prototype only: no real admin auth wired yet.');
    window.location.href = '/keeper/dashboard.php';
  });
});

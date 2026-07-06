// GRAVE RISING — Keeper admin shared JS
// Client-side presence check only; keeper/index.php verifies the real
// credentials server-side against KEEPER_ADMIN_USER / KEEPER_ADMIN_PASS_HASH
// and starts the session on success.

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('keeperLoginForm');
  if (!form) return;

  var keeperUser = document.getElementById('keeperUser');
  var keeperPass = document.getElementById('keeperPass');
  var keeperLoginError = document.getElementById('keeperLoginError');

  form.addEventListener('submit', function (event) {
    if (!keeperUser.value.trim() || !keeperPass.value) {
      event.preventDefault();
      graveSetFieldError(keeperLoginError, 'Username and password are required.');
      return;
    }

    graveSetFieldError(keeperLoginError, '');
    // Valid input — allow the native form submission to proceed to
    // keeper/index.php for server-side verification.
  });
});

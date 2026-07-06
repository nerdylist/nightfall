// GRAVE RISING — login page client-side validation
// Blocks submission on invalid input; otherwise lets the form POST to
// login.php, which verifies against SQLite and starts a session.

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('loginForm');
  if (!form) return;

  var identifier = document.getElementById('identifier');
  var password = document.getElementById('password');

  var identifierError = document.getElementById('identifierError');
  var passwordError = document.getElementById('passwordError');

  form.addEventListener('submit', function (event) {
    var valid = true;

    if (!identifier.value.trim()) {
      graveSetFieldError(identifierError, 'Username or email is required.');
      valid = false;
    } else {
      graveSetFieldError(identifierError, '');
    }

    if (!password.value) {
      graveSetFieldError(passwordError, 'Password is required.');
      valid = false;
    } else {
      graveSetFieldError(passwordError, '');
    }

    if (!valid) {
      event.preventDefault();
      return;
    }

    // Client-side validation passed — allow the native form submission to
    // proceed to login.php, which verifies against SQLite and starts a session.
  });
});

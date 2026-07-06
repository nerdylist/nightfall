// GRAVE RISING — login page client-side validation (no real submission logic)

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('loginForm');
  if (!form) return;

  var identifier = document.getElementById('identifier');
  var password = document.getElementById('password');

  var identifierError = document.getElementById('identifierError');
  var passwordError = document.getElementById('passwordError');

  form.addEventListener('submit', function (event) {
    event.preventDefault();
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
      return;
    }

    // BACKEND WIRING GOES HERE — POST to login handler, verify against SQLite users table, start session
    console.log('Prototype only: form validated, no submission wired yet.');
  });
});

// GRAVE RISING — register page client-side validation (no real submission logic)

document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('registerForm');
  if (!form) return;

  var email = document.getElementById('email');
  var username = document.getElementById('username');
  var password = document.getElementById('password');
  var passwordConfirm = document.getElementById('passwordConfirm');

  var emailError = document.getElementById('emailError');
  var usernameError = document.getElementById('usernameError');
  var passwordError = document.getElementById('passwordError');
  var passwordConfirmError = document.getElementById('passwordConfirmError');

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var valid = true;

    if (!email.value.trim()) {
      graveSetFieldError(emailError, 'Email is required.');
      valid = false;
    } else if (!graveIsValidEmail(email.value.trim())) {
      graveSetFieldError(emailError, 'Enter a valid email address.');
      valid = false;
    } else {
      graveSetFieldError(emailError, '');
    }

    if (!username.value.trim()) {
      graveSetFieldError(usernameError, 'Username is required.');
      valid = false;
    } else {
      graveSetFieldError(usernameError, '');
    }

    if (!password.value) {
      graveSetFieldError(passwordError, 'Password is required.');
      valid = false;
    } else if (password.value.length < 8) {
      graveSetFieldError(passwordError, 'Password must be at least 8 characters.');
      valid = false;
    } else {
      graveSetFieldError(passwordError, '');
    }

    if (!passwordConfirm.value) {
      graveSetFieldError(passwordConfirmError, 'Please confirm your password.');
      valid = false;
    } else if (passwordConfirm.value !== password.value) {
      graveSetFieldError(passwordConfirmError, 'Passwords do not match.');
      valid = false;
    } else {
      graveSetFieldError(passwordConfirmError, '');
    }

    if (!valid) {
      return;
    }

    // BACKEND WIRING GOES HERE — POST to register handler, insert into SQLite users table
    // Example (future): fetch('/register-handler.php', { method: 'POST', body: new FormData(form) })
    console.log('Prototype only: form validated, no submission wired yet.');
  });
});

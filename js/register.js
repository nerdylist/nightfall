// GRAVE RISING — register page client-side validation
// Blocks submission on invalid input; otherwise lets the form POST to
// register.php, which handles it server-side against SQLite.

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
      event.preventDefault();
      return;
    }

    // Client-side validation passed — allow the native form submission to
    // proceed to register.php, which handles it server-side against SQLite.
  });
});

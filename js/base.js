// GRAVE RISING — shared base helpers used across pages

/**
 * Basic email format check. Not exhaustive RFC validation —
 * good enough for client-side UX hinting.
 */
function graveIsValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

/**
 * Show/hide an inline error message element by toggling a class.
 */
function graveSetFieldError(errorEl, message) {
  if (!errorEl) return;
  if (message) {
    errorEl.textContent = message;
    errorEl.classList.add('is-visible');
  } else {
    errorEl.textContent = '';
    errorEl.classList.remove('is-visible');
  }
}

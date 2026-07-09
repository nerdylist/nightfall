/* Header search: icon trigger expands into an input within the nav row.
   Click to open + autofocus; Esc or click-outside closes. Submission behavior
   is unchanged from the old always-visible search input (no action wired —
   it does not navigate or filter anything). */
(function () {
  'use strict';

  function init() {
    var wrap = document.querySelector('.site-nav__search');
    if (!wrap) {
      return;
    }
    var trigger = wrap.querySelector('.site-nav__search-trigger');
    var input = wrap.querySelector('.site-nav__search-input');
    if (!trigger || !input) {
      return;
    }

    function isOpen() {
      return wrap.classList.contains('is-open');
    }

    function open() {
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      input.focus();
    }

    function close(focusTrigger) {
      wrap.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
      if (focusTrigger) {
        trigger.focus();
      }
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) {
        close(false);
      } else {
        open();
      }
    });

    document.addEventListener('click', function (e) {
      var target = e.target;
      var inside = target && target.closest ? target.closest('.site-nav__search') : null;
      if (!inside && isOpen()) {
        close(false);
      }
    });

    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && isOpen()) {
        close(true);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

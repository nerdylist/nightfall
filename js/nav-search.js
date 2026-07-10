/* Header search: icon trigger expands an input OVERLAY across the middle
   (menu) region of the nav row — the menu never reflows. Click to open +
   autofocus; Esc or click-outside closes. Submission behavior is unchanged
   from the old always-visible search input (no action wired — it does not
   navigate or filter anything). */
(function () {
  'use strict';

  function init() {
    var nav = document.querySelector('.site-nav');
    if (!nav) {
      return;
    }
    // The trigger icon lives in .site-nav__search; the input lives in the
    // .site-nav__search-form overlay inside .site-nav__middle.
    var wrap = nav.querySelector('.site-nav__search');
    var trigger = nav.querySelector('.site-nav__search-trigger');
    var input = nav.querySelector('.site-nav__search-input');
    if (!wrap || !trigger || !input) {
      return;
    }

    function isOpen() {
      return wrap.classList.contains('is-open');
    }

    function open() {
      wrap.classList.add('is-open');
      nav.classList.add('site-nav--search-open');
      trigger.setAttribute('aria-expanded', 'true');
      input.focus();
    }

    function close(focusTrigger) {
      wrap.classList.remove('is-open');
      nav.classList.remove('site-nav--search-open');
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
      var inside = target && target.closest
        ? target.closest('.site-nav__search, .site-nav__search-form')
        : null;
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

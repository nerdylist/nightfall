/* Mobile/tablet hamburger menu: the toggle button opens a dropdown panel
   (.site-nav__collapse) holding the menu, search, and auth CTAs. Below the
   CSS hamburger breakpoint (<=1024px) the panel is collapsed by default and
   revealed by toggling .is-menu-open on .site-nav. Click to open/close; Esc,
   click-outside, tapping a link, or resizing up to desktop all close it.
   Desktop (>1024px) is unaffected — the toggle is display:none there. */
(function () {
  'use strict';

  function init() {
    var nav = document.querySelector('.site-nav');
    if (!nav) {
      return;
    }
    var toggle = nav.querySelector('.site-nav__toggle');
    var collapse = nav.querySelector('.site-nav__collapse');
    if (!toggle || !collapse) {
      return;
    }

    function isOpen() {
      return nav.classList.contains('is-menu-open');
    }

    function open() {
      nav.classList.add('is-menu-open');
      toggle.setAttribute('aria-expanded', 'true');
    }

    function close(focusToggle) {
      nav.classList.remove('is-menu-open');
      toggle.setAttribute('aria-expanded', 'false');
      if (focusToggle) {
        toggle.focus();
      }
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) {
        close(false);
      } else {
        open();
      }
    });

    // Close when a menu link is tapped (navigating away or same-page anchor).
    collapse.addEventListener('click', function (e) {
      var target = e.target;
      if (target && target.closest && target.closest('.site-nav__link')) {
        close(false);
      }
    });

    // Click outside the nav closes the panel.
    document.addEventListener('click', function (e) {
      var target = e.target;
      var inside = target && target.closest ? target.closest('.site-nav') : null;
      if (!inside && isOpen()) {
        close(false);
      }
    });

    // Esc closes and returns focus to the toggle.
    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && isOpen()) {
        close(true);
      }
    });

    // Resizing up to desktop (where the toggle is hidden) closes the panel so
    // it can't get stuck open when the layout switches back to inline.
    window.addEventListener('resize', function () {
      if (isOpen() && window.matchMedia('(min-width: 1025px)').matches) {
        close(false);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

// THE DEAD LAST — Keeper admin shared JS.
// Collapsible sidebar: on narrow screens the hamburger toggles .is-nav-open on
// the shell, sliding the sidebar in over a scrim. Closes on scrim click, on Esc,
// when a nav link is tapped, and when resizing back up to the desktop layout.
(function () {
  'use strict';

  function init() {
    var shell = document.querySelector('.keeper-shell');
    var toggle = document.getElementById('keeper-nav-toggle');
    var scrim = document.getElementById('keeper-scrim');
    var sidebar = document.getElementById('keeper-sidebar');
    if (!shell || !toggle) {
      return;
    }

    function isOpen() {
      return shell.classList.contains('is-nav-open');
    }

    function open() {
      shell.classList.add('is-nav-open');
      toggle.setAttribute('aria-expanded', 'true');
      if (scrim) { scrim.hidden = false; }
    }

    function close(focusToggle) {
      shell.classList.remove('is-nav-open');
      toggle.setAttribute('aria-expanded', 'false');
      if (scrim) { scrim.hidden = true; }
      if (focusToggle) { toggle.focus(); }
    }

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) { close(false); } else { open(); }
    });

    if (scrim) {
      scrim.addEventListener('click', function () { close(false); });
    }

    // Tapping a nav link closes the overlay (it navigates anyway).
    if (sidebar) {
      sidebar.addEventListener('click', function (e) {
        var t = e.target;
        if (t && t.closest && t.closest('.keeper-nav__link') && isOpen()) {
          close(false);
        }
      });
    }

    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && isOpen()) {
        close(true);
      }
    });

    // Resizing up to the desktop layout (where the sidebar is fixed) clears the
    // open state so it can't get stuck.
    window.addEventListener('resize', function () {
      if (isOpen() && window.matchMedia('(min-width: 901px)').matches) {
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

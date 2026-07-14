// THE DEAD LAST — Keeper > Items. Opens/closes the Add/Edit item modal.
// "Add Item" opens it; the ✕, Cancel, backdrop click, and Esc close it. When
// the page loads mid-edit (?edit=…), the modal is already rendered open by PHP.
(function () {
  'use strict';

  function init() {
    var modal = document.getElementById('item-modal');
    if (!modal) {
      return;
    }
    var openers = document.querySelectorAll('[data-open-item-modal]');
    var closers = modal.querySelectorAll('[data-close-item-modal]');

    function open() {
      modal.hidden = false;
      modal.classList.add('is-open');
      // Focus the first field for quick entry.
      var first = modal.querySelector('input, textarea, select');
      if (first) { first.focus(); }
    }

    function close() {
      modal.hidden = true;
      modal.classList.remove('is-open');
    }

    openers.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        open();
      });
    });

    // Close controls: the ✕ and Cancel are also real links to
    // /keeper/items.php (so a mid-edit state resets); intercept to close
    // instantly without a reload when we're just adding.
    closers.forEach(function (el) {
      el.addEventListener('click', function (e) {
        // If we're in edit mode (URL has ?edit=), let the link navigate so the
        // form clears; otherwise just close the overlay in place.
        if (/[?&]edit=/.test(window.location.search)) {
          return; // allow default navigation to /keeper/items.php
        }
        e.preventDefault();
        close();
      });
    });

    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && !modal.hidden) {
        if (/[?&]edit=/.test(window.location.search)) {
          window.location.href = '/keeper/items.php';
        } else {
          close();
        }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

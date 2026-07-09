(function () {
  'use strict';

  var STORAGE_KEY = 'forum-theme';
  var VALID_THEMES = ['midnight', 'dusk', 'light', 'darkness'];

  function isValidTheme(theme) {
    return VALID_THEMES.indexOf(theme) !== -1;
  }

  function getOptions() {
    return document.querySelectorAll('.theme-option');
  }

  function markActive(theme) {
    var options = getOptions();
    for (var i = 0; i < options.length; i++) {
      var option = options[i];
      if (option.getAttribute('data-theme') === theme) {
        option.classList.add('active');
      } else {
        option.classList.remove('active');
      }
    }
  }

  function applyTheme(theme) {
    if (!isValidTheme(theme)) {
      return;
    }
    document.documentElement.dataset.theme = theme;
    markActive(theme);
  }

  // Dropdown toggle for the palette-icon theme menu. Mirrors the user-menu
  // pattern: click to toggle, click-outside / Escape to close.
  function initDropdown(switcher) {
    var trigger = switcher.querySelector('.theme-trigger');
    var dropdown = switcher.querySelector('.theme-dropdown');
    if (!trigger || !dropdown) {
      return null;
    }

    function isOpen() {
      return switcher.classList.contains('open');
    }
    function open() {
      switcher.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
    }
    function close() {
      switcher.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) {
        close();
      } else {
        open();
      }
    });

    document.addEventListener('click', function (e) {
      var target = e.target;
      var inside = target && target.closest ? target.closest('.theme-switcher') : null;
      if (!inside) {
        close();
      }
    });

    document.addEventListener('keydown', function (e) {
      if ((e.key === 'Escape' || e.keyCode === 27) && isOpen()) {
        close();
        trigger.focus();
      }
    });

    return { close: close };
  }

  function init() {
    var switcher = document.querySelector('.theme-switcher');
    var options = getOptions();
    if (!switcher || !options.length) {
      return;
    }

    var menu = initDropdown(switcher);

    var stored = null;
    try {
      stored = localStorage.getItem(STORAGE_KEY);
    } catch (e) {
      stored = null;
    }

    var current = isValidTheme(stored)
      ? stored
      : document.documentElement.dataset.theme;

    if (isValidTheme(current)) {
      document.documentElement.dataset.theme = current;
    }
    markActive(document.documentElement.dataset.theme);

    for (var i = 0; i < options.length; i++) {
      options[i].addEventListener('click', function (e) {
        var theme = this.getAttribute('data-theme');
        if (!isValidTheme(theme)) {
          return;
        }
        applyTheme(theme);
        try {
          localStorage.setItem(STORAGE_KEY, theme);
        } catch (err) {
          /* ignore storage errors */
        }
        if (menu) {
          menu.close();
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

/* ---- User menu dropdown: toggle, ARIA, keyboard ---- */
(function () {
  'use strict';

  function init() {
    var menu = document.querySelector('.user-menu');
    if (!menu) {
      return;
    }
    var trigger = menu.querySelector('.user-menu-trigger');
    var dropdown = menu.querySelector('.user-dropdown');
    if (!trigger || !dropdown) {
      return;
    }

    function firstItem() {
      return dropdown.querySelector('a, button');
    }

    function open(focusItem) {
      menu.classList.add('open');
      trigger.setAttribute('aria-expanded', 'true');
      if (focusItem) {
        var item = firstItem();
        if (item) {
          item.focus();
        }
      }
    }

    function close(focusTrigger) {
      menu.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      if (focusTrigger) {
        trigger.focus();
      }
    }

    function isOpen() {
      return menu.classList.contains('open');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (isOpen()) {
        close(false);
      } else {
        open(false);
      }
    });

    trigger.addEventListener('keydown', function (e) {
      var key = e.key;
      if (key === 'Enter' || key === ' ' || key === 'Spacebar' || e.keyCode === 13 || e.keyCode === 32) {
        e.preventDefault();
        if (!isOpen()) {
          open(true);
        } else {
          open(true);
        }
      }
    });

    dropdown.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        e.preventDefault();
        close(true);
      }
    });

    document.addEventListener('click', function (e) {
      var target = e.target;
      var inside = target && target.closest ? target.closest('.user-menu') : null;
      if (!inside) {
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

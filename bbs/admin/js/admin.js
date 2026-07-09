(function () {
  'use strict';

  function ready() {
    var els = document.querySelectorAll('[data-confirm]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      var evt = (el.tagName === 'FORM') ? 'submit' : 'click';
      el.addEventListener(evt, function (e) {
        if (!confirm(this.getAttribute('data-confirm'))) {
          e.preventDefault();
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ready);
  } else {
    ready();
  }
})();
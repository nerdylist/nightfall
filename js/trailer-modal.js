/* THE DEAD LAST — hero "Watch Trailer" modal.
   Native <dialog> shown with showModal() so it lives in the browser top layer:
   centered on the viewport and immune to the body's grayscale filter. Closes on
   the X, backdrop click, or Esc. Locks page scroll; pauses + rewinds on close. */
(function () {
  'use strict';

  function init() {
    var modal = document.getElementById('trailer-modal');
    var video = document.getElementById('trailer-video');
    var openers = document.querySelectorAll('[data-open-trailer]');
    if (!modal || !video || !openers.length) {
      return;
    }

    function open() {
      if (typeof modal.showModal === 'function' && !modal.open) {
        modal.showModal();
      } else {
        modal.setAttribute('open', '');
      }
      document.body.classList.add('trailer-open');
      var p = video.play();
      if (p && typeof p.catch === 'function') { p.catch(function () {}); }
    }

    function close() {
      if (typeof modal.close === 'function' && modal.open) {
        modal.close();
      } else {
        modal.removeAttribute('open');
      }
      document.body.classList.remove('trailer-open');
      video.pause();
      try { video.currentTime = 0; } catch (e) {}
    }

    openers.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        open();
      });
    });

    // X button (and any explicit close control).
    modal.querySelectorAll('[data-close-trailer]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        close();
      });
    });

    // Backdrop click: a click landing on the <dialog> element itself (outside
    // the stage/video) closes it.
    modal.addEventListener('click', function (e) {
      if (e.target === modal) {
        close();
      }
    });

    // Native Esc fires the dialog 'cancel' event; run our teardown too.
    modal.addEventListener('cancel', function (e) {
      e.preventDefault();
      close();
    });
    // And 'close' (any close path) ensures scroll unlock + video stop.
    modal.addEventListener('close', function () {
      document.body.classList.remove('trailer-open');
      video.pause();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

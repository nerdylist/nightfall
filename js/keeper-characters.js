/* Keeper > Characters — per-row Messages modal open/close.
   A card's Messages button (data-open-messages="<modal-id>") reveals that
   character's line-editor modal; backdrop / close / Cancel / Esc dismiss it. */
(function () {
  'use strict';

  function open(modal) {
    if (!modal) { return; }
    modal.hidden = false;
    document.body.classList.add('keeper-modal-open');
    var input = modal.querySelector('input[type="text"], textarea');
    if (input) { input.focus(); }
  }

  function close(modal) {
    if (!modal) { return; }
    modal.hidden = true;
    if (!document.querySelector('.keeper-modal:not([hidden])')) {
      document.body.classList.remove('keeper-modal-open');
    }
  }

  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-open-messages]');
    if (opener) {
      e.preventDefault();
      open(document.getElementById(opener.getAttribute('data-open-messages')));
      return;
    }
    if (e.target.closest('[data-close-messages]')) {
      e.preventDefault();
      close(e.target.closest('.keeper-modal'));
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' || e.keyCode === 27) {
      var openModal = document.querySelector('.keeper-modal:not([hidden])');
      if (openModal) { close(openModal); }
    }
  });
})();

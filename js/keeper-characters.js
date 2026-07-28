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

  // ---- HP bands repeater (add/remove {from,to,mode,value} rows) ----
  document.addEventListener('click', function (e) {
    var addBtn = e.target.closest('[data-band-add]');
    if (addBtn) {
      e.preventDefault();
      var wrap = addBtn.closest('[data-bands]');
      var tpl = wrap && wrap.querySelector('[data-band-template]');
      var rows = wrap && wrap.querySelector('[data-bands-rows]');
      if (tpl && rows) {
        var frag = tpl.content.cloneNode(true);
        rows.appendChild(frag);
        var added = rows.lastElementChild;
        var first = added && added.querySelector('input');
        if (first) { first.focus(); }
      }
      return;
    }
    var rm = e.target.closest('[data-band-remove]');
    if (rm) {
      e.preventDefault();
      var row = rm.closest('[data-band-row]');
      if (row) { row.remove(); }
    }
  });
})();

// THE DEAD LAST — Keeper > Survivors. Opens/prefills the survivor game-data
// modal from each row's data-edit-survivor JSON payload.
(function () {
  'use strict';

  function setVal(id, v) {
    var el = document.getElementById(id);
    if (el) { el.value = (v === null || v === undefined) ? '' : v; }
  }
  function setText(id, v) {
    var el = document.getElementById(id);
    if (el) { el.textContent = (v === null || v === undefined || v === '') ? '—' : v; }
  }

  function openModal(m) {
    if (m) { m.hidden = false; document.body.classList.add('keeper-modal-open'); }
  }
  function closeModal(m) {
    if (m) { m.hidden = true; document.body.classList.remove('keeper-modal-open'); }
  }

  var ATTRS = ['str', 'agi', 'end', 'int', 'awa', 'luk', 'foc', 'fai'];

  function init() {
    var modal = document.getElementById('survivor-modal');

    document.querySelectorAll('[data-edit-survivor]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d;
        try { d = JSON.parse(btn.getAttribute('data-edit-survivor')); } catch (e) { return; }

        setVal('es-id', d.id);
        setVal('es-name', d.name);
        setVal('es-skin', d.skin);
        setVal('es-outcome', d.outcome);
        setVal('es-xp', d.xp);
        setVal('es-points_spent', d.points_spent);
        ATTRS.forEach(function (a) { setVal('es-' + a, d[a]); });

        // Read-only context.
        setText('es-owner', d.owner);
        setText('es-ref', d.ref);
        setText('es-skin-ctx', d.skin);
        setText('es-started', d.started_at);
        setText('es-earned', d.points_earned);

        var t = document.getElementById('survivor-modal-title');
        if (t) { t.textContent = 'Survivor #' + (d.id || '') + ' — Game Data'; }

        openModal(modal);
      });
    });

    document.querySelectorAll('[data-close-survivor-modal]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(modal); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        if (modal && !modal.hidden) { closeModal(modal); }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

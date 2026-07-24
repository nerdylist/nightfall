// THE DEAD LAST — Keeper > Users. Opens/prefills the account-edit modal and
// the game-data (player_stats) modal from each row's data-* JSON payload.
(function () {
  'use strict';

  function setVal(id, v) {
    var el = document.getElementById(id);
    if (el) { el.value = (v === null || v === undefined) ? '' : v; }
  }

  function openModal(m) {
    if (m) { m.hidden = false; document.body.classList.add('keeper-modal-open'); }
  }
  function closeModal(m) {
    if (m) { m.hidden = true; document.body.classList.remove('keeper-modal-open'); }
  }

  function init() {
    var userModal = document.getElementById('user-modal');
    var statsModal = document.getElementById('stats-modal');

    // Account edit
    document.querySelectorAll('[data-edit-user]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d;
        try { d = JSON.parse(btn.getAttribute('data-edit-user')); } catch (e) { return; }
        setVal('eu-id', d.id);
        setVal('rp-id', d.id);
        setVal('eu-username', d.username);
        setVal('eu-display', d.display_name);
        setVal('eu-email', d.email);
        setVal('eu-role', d.role);
        setVal('eu-status', d.status);
        setVal('eu-reputation', d.reputation);
        var t = document.getElementById('user-modal-title');
        if (t) { t.textContent = 'Edit User — ' + (d.username || ''); }
        openModal(userModal);
      });
    });

    // Game data edit
    document.querySelectorAll('[data-edit-stats]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d;
        try { d = JSON.parse(btn.getAttribute('data-edit-stats')); } catch (e) { return; }
        setVal('es-id', d.id);
        var uname = document.getElementById('es-username');
        if (uname) { uname.textContent = d.username || ('#' + d.id); }
        // Every field id is es-<column>; fill from the payload.
        Object.keys(d).forEach(function (k) {
          if (k === 'id' || k === 'username') { return; }
          setVal('es-' + k, d[k]);
        });
        openModal(statsModal);
      });
    });

    // Close controls
    document.querySelectorAll('[data-close-user-modal]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(userModal); });
    });
    document.querySelectorAll('[data-close-stats-modal]').forEach(function (el) {
      el.addEventListener('click', function () { closeModal(statsModal); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.keyCode === 27) {
        if (userModal && !userModal.hidden) { closeModal(userModal); }
        if (statsModal && !statsModal.hidden) { closeModal(statsModal); }
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

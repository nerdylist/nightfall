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

/* ---- New-character archetype auto-defaults ----
   On the ADD form only, a new enemy spawner is BORN with wave dials: pick the
   archetype for the chosen type (Zombie→SHAMBLER, NPC/Enemy→HEAVY; Human→none)
   and fill the dial fields + band rows. Re-applies when the type changes, but
   only while the user hasn't manually edited the dials (so we never stomp
   hand-entered values). Everything stays editable before save. */
(function () {
  'use strict';

  var dataEl = document.querySelector('[data-char-archetypes]');
  var form = document.querySelector('.keeper-chars-form');
  if (!dataEl || !form) { return; } // edit form / no data → nothing to do

  var cfg;
  try { cfg = JSON.parse(dataEl.textContent || '{}'); } catch (e) { return; }
  var archetypes = cfg.archetypes || {};
  var byType = cfg.byType || {};

  var typeSel = form.querySelector('select[name="type"]');
  var bands = form.querySelector('[data-bands]');
  var rowsWrap = bands && bands.querySelector('[data-bands-rows]');
  var tpl = bands && bands.querySelector('[data-band-template]');
  if (!typeSel || !rowsWrap || !tpl) { return; }

  var dirty = false; // user has hand-edited the dials → stop auto-filling

  function setVal(name, v) {
    var el = form.querySelector('[name="' + name + '"]');
    if (el) { el.value = (v === null || v === undefined) ? '' : v; }
  }

  function applyArchetype(name) {
    var a = archetypes[name];
    if (!a) { // no archetype for this type (e.g. Human): clear the dials
      setVal('hp_base', ''); setVal('wave_min', ''); setVal('hp_cap', '');
      rowsWrap.innerHTML = '';
      return;
    }
    setVal('hp_base', a.hp_base);
    setVal('wave_min', a.wave_min);
    setVal('hp_cap', a.hp_cap);
    setVal('xp_value', a.xp_value);
    setVal('wave_max', '');
    // Rebuild band rows from the template.
    rowsWrap.innerHTML = '';
    (a.hp_bands || []).forEach(function (b) {
      rowsWrap.appendChild(tpl.content.cloneNode(true));
      var row = rowsWrap.lastElementChild;
      var set = function (n, v) {
        var el = row.querySelector('[name="' + n + '[]"]');
        if (el) { el.value = (v === null || v === undefined) ? '' : v; }
      };
      set('band_from', b.from);
      set('band_to', b.to);
      var modeEl = row.querySelector('[name="band_mode[]"]');
      if (modeEl) { modeEl.value = (b.mode === 'flat') ? 'flat' : 'pct'; }
      set('band_value', b.value);
      set('band_spawn_weight', b.spawn_weight);
      set('band_max_alive', b.max_alive);
      set('band_xp_value', b.xp_value);
    });
  }

  // Mark dirty on any manual edit inside the wave fieldset.
  var fieldset = form.querySelector('.keeper-chars-wave');
  if (fieldset) {
    fieldset.addEventListener('input', function () { dirty = true; });
  }

  typeSel.addEventListener('change', function () {
    if (dirty) { return; }
    applyArchetype(byType[typeSel.value]);
  });

  // Initial fill for the default-selected type (unless the form already has
  // dial values, e.g. a validation re-render).
  var hpBase = form.querySelector('[name="hp_base"]');
  if (!hpBase || hpBase.value === '') {
    applyArchetype(byType[typeSel.value]);
  }
})();

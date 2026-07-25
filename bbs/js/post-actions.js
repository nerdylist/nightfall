/* post-actions.js — social action row: Share, Copy Link, React (emoji picker + chips).
   Vanilla JS, no dependencies. All browser APIs are feature-checked and wrapped. */
(function () {
  'use strict';

  var row = document.querySelector('.post-actions');
  if (!row) { return; }

  // Actions are siblings of .post-body, so they never reach the modal opener.
  // Guard anyway in case of future markup changes.
  row.addEventListener('click', function (e) { e.stopPropagation(); });

  var threadTitle = row.getAttribute('data-thread-title') || document.title;
  var shareBtn = row.querySelector('[data-action="share"]');
  var copyBtn = row.querySelector('[data-action="copy"]');
  var reactBtn = row.querySelector('[data-action="react"]');
  var picker = row.querySelector('.reaction-picker');
  var reactionsArea = document.querySelector('.post-reactions');

  function flashCopied(btn) {
    if (!btn) { return; }
    // Buttons are icon-only; surface "Copied!" via the tooltip + an accent flash.
    var label = btn.querySelector('.post-action-label');
    var origLabel = label ? label.textContent : null;
    var origTitle = btn.getAttribute('title');
    btn.classList.add('copied');
    if (label) { label.textContent = 'Copied!'; }
    btn.setAttribute('title', 'Copied!');
    window.setTimeout(function () {
      btn.classList.remove('copied');
      if (label && origLabel !== null) { label.textContent = origLabel; }
      if (origTitle !== null) { btn.setAttribute('title', origTitle); }
    }, 1500);
  }

  function copyUrl() {
    var url = location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      try {
        return navigator.clipboard.writeText(url).then(
          function () { return true; },
          function () { return legacyCopy(url); }
        );
      } catch (e) {
        return Promise.resolve(legacyCopy(url));
      }
    }
    return Promise.resolve(legacyCopy(url));
  }

  function legacyCopy(text) {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'absolute';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e) {
      return false;
    }
  }

  /* ---- Copy Link ---- */
  if (copyBtn) {
    copyBtn.addEventListener('click', function () {
      copyUrl().then(function (ok) {
        if (ok) { flashCopied(copyBtn); }
      });
    });
  }

  /* ---- Share (Web Share API, falls back to copy) ---- */
  if (shareBtn) {
    shareBtn.addEventListener('click', function () {
      if (navigator.share) {
        try {
          var p = navigator.share({ title: threadTitle, url: location.href });
          if (p && typeof p.then === 'function') {
            p.catch(function () { /* user cancelled or failed — silent */ });
          }
          return;
        } catch (e) {
          // fall through to copy fallback
        }
      }
      copyUrl().then(function (ok) {
        if (ok) { flashCopied(shareBtn); }
      });
    });
  }

  /* ---- React: picker + chips ---- */
  function openPicker() {
    if (!picker) { return; }
    picker.hidden = false;
    if (reactBtn) { reactBtn.setAttribute('aria-expanded', 'true'); }
  }

  function closePicker() {
    if (!picker || picker.hidden) { return; }
    picker.hidden = true;
    if (reactBtn) { reactBtn.setAttribute('aria-expanded', 'false'); }
  }

  function togglePicker() {
    if (picker && picker.hidden) { openPicker(); } else { closePicker(); }
  }

  // PERSISTED REACTIONS (2026-07-25): the old version only painted chips in
  // the DOM — nothing survived a refresh. Toggles now POST to react.php and
  // the chip row is rebuilt from the server's JSON truth (counts + mine).
  function rebuildChips(counts, mine) {
    if (!reactionsArea) { return; }
    reactionsArea.innerHTML = '';
    Object.keys(counts).forEach(function (emoji) {
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'reaction-chip' + (mine.indexOf(emoji) !== -1 ? ' active' : '');
      chip.setAttribute('data-emoji', emoji);
      chip.setAttribute('aria-label', 'Toggle ' + emoji + ' reaction');

      var emojiSpan = document.createElement('span');
      emojiSpan.className = 'reaction-chip-emoji';
      emojiSpan.textContent = emoji;

      var countSpan = document.createElement('span');
      countSpan.className = 'reaction-chip-count';
      countSpan.textContent = String(counts[emoji]);

      chip.appendChild(emojiSpan);
      chip.appendChild(countSpan);
      reactionsArea.appendChild(chip);
    });
  }

  var reactBusy = false;
  function toggleReaction(emoji) {
    if (!reactionsArea || reactBusy) { return; }
    if (!reactionsArea.getAttribute('data-can-react')) {
      window.location.href = '/login?next=' + encodeURIComponent(window.location.pathname);
      return;
    }
    reactBusy = true;
    var body = new URLSearchParams();
    body.set('csrf_token', reactionsArea.getAttribute('data-csrf') || '');
    body.set('post_id', reactionsArea.getAttribute('data-post-id') || '0');
    body.set('emoji', emoji);
    fetch(reactionsArea.getAttribute('data-endpoint') || '/bbs/react.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (json && json.ok) { rebuildChips(json.counts || {}, json.mine || []); }
    }).catch(function () { /* leave chips as-is on network error */ })
      .finally(function () { reactBusy = false; });
  }

  // Chip clicks toggle off/on — delegated so server-rendered AND rebuilt
  // chips both work without per-chip listeners.
  if (reactionsArea) {
    reactionsArea.addEventListener('click', function (e) {
      var chip = e.target.closest ? e.target.closest('.reaction-chip') : null;
      if (chip) { toggleReaction(chip.getAttribute('data-emoji')); }
    });
  }

  if (reactBtn) {
    reactBtn.addEventListener('click', togglePicker);
  }

  if (picker) {
    var reactionButtons = picker.querySelectorAll('.reaction');
    Array.prototype.forEach.call(reactionButtons, function (btn) {
      btn.addEventListener('click', function () {
        var emoji = btn.getAttribute('data-emoji');
        if (emoji) { toggleReaction(emoji); }
        closePicker();
        if (reactBtn) { reactBtn.focus(); }
      });
    });
  }

  // Close on outside click.
  document.addEventListener('click', function (e) {
    if (!picker || picker.hidden) { return; }
    var wrap = row.querySelector('.reaction-wrap');
    if (wrap && !wrap.contains(e.target)) { closePicker(); }
  });

  // Close on Escape.
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && picker && !picker.hidden) {
      closePicker();
      if (reactBtn) { reactBtn.focus(); }
    }
  });
})();

// STAFF THREAD DELETE (2026-07-25): confirm before the form fires. The
// button only renders for admin/moderator; the server re-checks the role.
(function () {
  var delForm = document.querySelector('.post-delete-form');
  if (!delForm) { return; }
  delForm.addEventListener('submit', function (e) {
    if (!window.confirm('Delete this thread and all of its replies? This cannot be undone.')) {
      e.preventDefault();
    }
  });
})();

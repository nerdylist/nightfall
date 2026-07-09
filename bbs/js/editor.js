// editor.js — lightweight BBCode editor toolbar + image upload.
// Classic script, loaded globally. No-ops when no [data-bbcode-editor] exists.
(function () {
  'use strict';

  function setSelection(ta, start, end) {
    ta.focus();
    try { ta.setSelectionRange(start, end); } catch (e) {}
  }

  // Wrap the current selection with open/close, or insert empty tags with the
  // caret placed between them when there is no selection.
  function wrapSelection(ta, open, close) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var value = ta.value;
    var before = value.slice(0, start);
    var selected = value.slice(start, end);
    var after = value.slice(end);

    if (selected) {
      ta.value = before + open + selected + close + after;
      setSelection(ta, start + open.length, start + open.length + selected.length);
    } else {
      ta.value = before + open + close + after;
      var caret = start + open.length;
      setSelection(ta, caret, caret);
    }
  }

  // Replace the current selection (or insert at caret) with the given text,
  // placing the caret at the end of the inserted text.
  function insertAtCaret(ta, text) {
    var start = ta.selectionStart;
    var end = ta.selectionEnd;
    var value = ta.value;
    ta.value = value.slice(0, start) + text + value.slice(end);
    var caret = start + text.length;
    setSelection(ta, caret, caret);
  }

  function makeButton(iconClass, ariaLabel, title) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'editor-btn ' + iconClass;
    btn.setAttribute('aria-label', ariaLabel);
    btn.title = title;
    // Keep toolbar buttons out of the Tab sequence so Tab goes
    // subject -> textarea directly (buttons stay clickable).
    btn.tabIndex = -1;
    return btn;
  }

  function initEditor(wrapper) {
    var ta = wrapper.querySelector('[data-bbcode-textarea]');
    if (!ta) { return; }
    var status = wrapper.querySelector('[data-bbcode-status]');
    var csrf = wrapper.getAttribute('data-csrf') || '';

    function setStatus(msg) {
      if (status) { status.textContent = msg; }
    }

    var toolbar = document.createElement('div');
    toolbar.className = 'editor-toolbar';

    var bBold = makeButton('ic-bold', 'Bold', 'Bold (Ctrl+B)');
    var bItalic = makeButton('ic-italic', 'Italic', 'Italic (Ctrl+I)');
    var bUnderline = makeButton('ic-underline', 'Underline', 'Underline');
    var bStrike = makeButton('ic-strike', 'Strikethrough', 'Strikethrough');
    var bQuote = makeButton('ic-quote', 'Quote', 'Quote');
    var bCode = makeButton('ic-code', 'Code', 'Code');
    var bLink = makeButton('ic-link', 'Link', 'Link');
    var bImage = makeButton('ic-image', 'Image by URL', 'Image by URL');
    var bUpload = makeButton('ic-upload', 'Upload image', 'Upload image');

    bBold.addEventListener('click', function () { wrapSelection(ta, '[b]', '[/b]'); });
    bItalic.addEventListener('click', function () { wrapSelection(ta, '[i]', '[/i]'); });
    bUnderline.addEventListener('click', function () { wrapSelection(ta, '[u]', '[/u]'); });
    bStrike.addEventListener('click', function () { wrapSelection(ta, '[s]', '[/s]'); });
    bQuote.addEventListener('click', function () { wrapSelection(ta, '[quote]', '[/quote]'); });
    bCode.addEventListener('click', function () { wrapSelection(ta, '[code]', '[/code]'); });

    bLink.addEventListener('click', function () {
      var selected = ta.value.slice(ta.selectionStart, ta.selectionEnd);
      var url = prompt('Link URL:');
      if (!url) { return; }
      if (selected) {
        insertAtCaret(ta, '[url=' + url + ']' + selected + '[/url]');
      } else {
        var text = prompt('Link text (optional):');
        if (text) {
          insertAtCaret(ta, '[url=' + url + ']' + text + '[/url]');
        } else {
          insertAtCaret(ta, '[url]' + url + '[/url]');
        }
      }
    });

    bImage.addEventListener('click', function () {
      var url = prompt('Image URL:');
      if (!url) { return; }
      insertAtCaret(ta, '[img]' + url + '[/img]');
    });

    // Hidden file input, created once per editor.
    var fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/*';
    fileInput.style.display = 'none';
    wrapper.appendChild(fileInput);

    bUpload.addEventListener('click', function () { fileInput.click(); });

    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) { return; }
      var formData = new FormData();
      formData.append('image', file);
      formData.append('csrf_token', csrf);
      setStatus('Uploading…');
      fetch('upload.php', { method: 'POST', body: formData })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data && data.url) {
            insertAtCaret(ta, '[img]' + data.url + '[/img]');
            setStatus('Uploaded.');
          } else {
            setStatus((data && data.error) || 'Upload failed.');
          }
        })
        .catch(function () { setStatus('Upload failed.'); })
        .then(function () { fileInput.value = ''; });
    });

    var sep1 = document.createElement('span'); sep1.className = 'editor-sep';
    var sep2 = document.createElement('span'); sep2.className = 'editor-sep';
    [bBold, bItalic, bUnderline, bStrike, sep1, bQuote, bCode, sep2, bLink, bImage, bUpload]
      .forEach(function (el) { toolbar.appendChild(el); });

    ta.parentNode.insertBefore(toolbar, ta);

    // Minimal keyboard shortcuts.
    ta.addEventListener('keydown', function (e) {
      if (!(e.ctrlKey || e.metaKey)) { return; }
      var key = e.key.toLowerCase();
      if (key === 'b') { e.preventDefault(); wrapSelection(ta, '[b]', '[/b]'); }
      else if (key === 'i') { e.preventDefault(); wrapSelection(ta, '[i]', '[/i]'); }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var editors = document.querySelectorAll('[data-bbcode-editor]');
    for (var i = 0; i < editors.length; i++) {
      initEditor(editors[i]);
    }
  });
})();

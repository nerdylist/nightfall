(function () {
  'use strict';

  function crc32(str) {
    var c, table = crc32.table;
    if (!table) {
      table = crc32.table = [];
      for (var n = 0; n < 256; n++) {
        c = n;
        for (var k = 0; k < 8; k++) {
          c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
        }
        table[n] = c >>> 0;
      }
    }
    var crc = 0xFFFFFFFF;
    var bytes = unescape(encodeURIComponent(str));
    for (var i = 0; i < bytes.length; i++) {
      crc = (crc >>> 8) ^ table[(crc ^ bytes.charCodeAt(i)) & 0xFF];
    }
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }

  function init() {
    var chat = document.querySelector('.chat');
    var input = document.getElementById('chat-input');
    var sendBtn = document.getElementById('chat-send');
    var messages = document.querySelector('.chat-messages');

    if (!chat || !messages) {
      return;
    }

    var threadId = parseInt(chat.getAttribute('data-thread-id'), 10) || 0;
    var csrf = chat.getAttribute('data-csrf') || '';
    var canPost = chat.getAttribute('data-can-post') === '1';
    var lastId = parseInt(chat.getAttribute('data-last-id'), 10) || 0;
    var userId = parseInt(chat.getAttribute('data-user-id'), 10) || 0;

    var statusNode = null;

    function setStatus(text) {
      if (!statusNode) {
        statusNode = document.createElement('p');
        statusNode.className = 'chat-error';
        var composer = chat.querySelector('.chat-composer');
        if (composer && composer.nextSibling) {
          chat.insertBefore(statusNode, composer.nextSibling);
        } else {
          chat.appendChild(statusNode);
        }
      }
      statusNode.textContent = text;
    }

    function clearStatus() {
      if (statusNode) {
        statusNode.textContent = '';
      }
    }

    function appendMessage(m) {
      var empty = messages.querySelector('.chat-empty');
      if (empty) {
        empty.parentNode.removeChild(empty);
      }

      var line = document.createElement('div');
      line.className = 'chat-line';
      if (userId && parseInt(m.author_id, 10) === userId) {
        line.className += ' chat-line--me';
      }
      line.setAttribute('data-msg-id', m.id);

      var avatar = document.createElement('div');
      avatar.className = 'avatar avatar--md';
      var hue = crc32(m.author_name) % 360;
      avatar.style.setProperty('--avatar-bg', 'hsl(' + hue + ', 55%, 52%)');
      avatar.textContent = m.author_initials;

      var body = document.createElement('div');
      body.className = 'chat-body';

      var meta = document.createElement('div');
      meta.className = 'chat-meta';

      var user = document.createElement('span');
      user.className = 'chat-user';
      user.textContent = m.author_name;

      var time = document.createElement('span');
      time.className = 'chat-time';
      time.textContent = m.timestamp;

      var msg = document.createElement('div');
      msg.className = 'chat-text';
      msg.textContent = m.text;

      meta.appendChild(user);
      meta.appendChild(time);
      body.appendChild(meta);
      body.appendChild(msg);

      line.appendChild(avatar);
      line.appendChild(body);

      messages.appendChild(line);
      messages.scrollTop = messages.scrollHeight;
    }

    function sendMessage() {
      if (!canPost || !csrf) {
        return;
      }
      var text = input.value.trim();
      if (!text) {
        return;
      }

      input.disabled = true;
      sendBtn.disabled = true;

      var fd = new FormData();
      fd.append('csrf_token', csrf);
      fd.append('thread_id', threadId);
      fd.append('text', text);

      fetch('chat.php', { method: 'POST', body: fd }).then(function (r) {
        if (!r.ok) {
          throw new Error('send');
        }
        return r.json();
      }).then(function (m) {
        appendMessage(m);
        lastId = m.id;
        input.value = '';
        clearStatus();
        input.focus();
      }).catch(function () {
        setStatus('Could not send.');
      }).finally(function () {
        input.disabled = false;
        sendBtn.disabled = false;
      });
    }

    if (canPost && input && sendBtn) {
      sendBtn.addEventListener('click', function () {
        sendMessage();
      });

      input.addEventListener('keydown', function (e) {
        var isEnter = e.key === 'Enter' || e.keyCode === 13;
        if (isEnter && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });

      input.addEventListener('input', function () {
        clearStatus();
      });
    }

    var polling = false;

    function poll() {
      if (polling) {
        return;
      }
      if (document.hidden) {
        return;
      }
      polling = true;
      fetch('chat.php?thread_id=' + threadId + '&after_id=' + lastId).then(function (r) {
        return r.json();
      }).then(function (d) {
        (d.messages || []).forEach(function (m) {
          if (m.id > lastId) {
            appendMessage(m);
            lastId = m.id;
          }
        });
      }).catch(function () {}).finally(function () {
        polling = false;
      });
    }

    setInterval(poll, 4000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

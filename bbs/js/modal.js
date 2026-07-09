(function () {
  'use strict';

  function initModals() {
    var triggers = document.querySelectorAll('[data-open-modal]');
    if (!triggers.length) {
      return;
    }

    var lastTrigger = null;

    function openModal(modal, trigger) {
      if (!modal) {
        return;
      }
      lastTrigger = trigger || null;
      modal.hidden = false;
      var panel = modal.querySelector('.modal-panel');
      var closeBtn = modal.querySelector('.modal-close');
      if (closeBtn) {
        closeBtn.focus();
      } else if (panel) {
        panel.focus();
      }
    }

    function closeModal(modal) {
      if (!modal || modal.hidden) {
        return;
      }
      modal.hidden = true;
      if (lastTrigger && typeof lastTrigger.focus === 'function') {
        lastTrigger.focus();
      }
      lastTrigger = null;
    }

    function visibleModal() {
      var modals = document.querySelectorAll('.modal');
      for (var i = 0; i < modals.length; i++) {
        if (!modals[i].hidden) {
          return modals[i];
        }
      }
      return null;
    }

    triggers.forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        var id = trigger.getAttribute('data-open-modal');
        var modal = id ? document.getElementById(id) : null;
        openModal(modal, trigger);
      });
    });

    document.querySelectorAll('.modal').forEach(function (modal) {
      modal.addEventListener('click', function (e) {
        var target = e.target;
        if (target && target.closest && target.closest('[data-close]')) {
          closeModal(modal);
        }
      });
    });

    document.addEventListener('keydown', function (e) {
      var isEsc = e.key === 'Escape' || e.keyCode === 27;
      if (isEsc) {
        closeModal(visibleModal());
      }
    });
  }

  function initChatScroll() {
    var messages = document.querySelector('.chat-messages');
    if (!messages) {
      return;
    }
    messages.scrollTop = messages.scrollHeight;
  }

  function init() {
    initModals();
    initChatScroll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

/* THE DEAD LAST — /media audio player.
   One <audio> element drives a playlist: click a track to play it, play/pause,
   prev/next, seek, live time, and auto-advance to the next track (looping the
   list). Track sources come from the page's playlist items (Active music). */
(function () {
  'use strict';

  function fmt(t) {
    if (!isFinite(t) || t < 0) { return '0:00'; }
    var m = Math.floor(t / 60);
    var s = Math.floor(t % 60);
    return m + ':' + (s < 10 ? '0' : '') + s;
  }

  function init() {
    var root = document.getElementById('media-player');
    var audio = document.getElementById('mp-audio');
    if (!root || !audio) { return; }

    var items = Array.prototype.slice.call(document.querySelectorAll('.media-playlist__item'));
    if (!items.length) { return; }

    var titleEl = document.getElementById('mp-title');
    var playBtn = document.getElementById('mp-play');
    var prevBtn = document.getElementById('mp-prev');
    var nextBtn = document.getElementById('mp-next');
    var seek = document.getElementById('mp-seek');
    var curEl = document.getElementById('mp-current');
    var durEl = document.getElementById('mp-duration');

    var canvas = document.getElementById('mp-viz');

    var current = 0;
    var seeking = false;

    // ---- Black & white frequency visualizer (Web Audio API) ----
    var audioCtx = null, analyser = null, freq = null, vizRAF = null;

    function setupViz() {
      // MediaElementSource can only be created once per <audio>; AudioContext
      // must be resumed from a user gesture (we call this from the play click).
      if (analyser || !canvas || !window.AudioContext && !window.webkitAudioContext) {
        if (audioCtx && audioCtx.state === 'suspended') { audioCtx.resume(); }
        return;
      }
      try {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        audioCtx = new Ctx();
        var src = audioCtx.createMediaElementSource(audio);
        analyser = audioCtx.createAnalyser();
        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.82;
        src.connect(analyser);
        analyser.connect(audioCtx.destination);
        freq = new Uint8Array(analyser.frequencyBinCount);
      } catch (e) {
        analyser = null; // fail silently — playback still works
      }
    }

    function drawViz() {
      vizRAF = requestAnimationFrame(drawViz);
      if (!analyser || !canvas) { return; }
      var ctx = canvas.getContext('2d');
      var w = canvas.width, h = canvas.height;
      ctx.clearRect(0, 0, w, h);
      analyser.getByteFrequencyData(freq);

      var bars = 48;
      var step = Math.floor(freq.length / bars) || 1;
      var gap = 2;
      var bw = (w - (bars - 1) * gap) / bars;

      for (var i = 0; i < bars; i++) {
        var v = freq[i * step] / 255;           // 0..1
        var bh = Math.max(2, v * h);
        var x = i * (bw + gap);
        var y = h - bh;
        // White bars fading to grey with height — pure B&W.
        var g = ctx.createLinearGradient(0, y, 0, h);
        g.addColorStop(0, 'rgba(255,255,255,' + (0.55 + v * 0.45) + ')');
        g.addColorStop(1, 'rgba(255,255,255,0.12)');
        ctx.fillStyle = g;
        ctx.fillRect(x, y, bw, bh);
        // reflection
        ctx.fillStyle = 'rgba(255,255,255,0.05)';
        ctx.fillRect(x, h - 1, bw, 1);
      }
    }

    function tracks(i) { return items[i]; }

    function markCurrent() {
      items.forEach(function (el, i) {
        el.classList.toggle('is-current', i === current);
      });
    }

    function load(index, autoplay) {
      current = (index + items.length) % items.length;
      var el = tracks(current);
      audio.src = el.getAttribute('data-src');
      titleEl.textContent = el.getAttribute('data-title') || '';
      markCurrent();
      saveState();
      if (autoplay) { play(); }
    }

    function play() {
      var p = audio.play();
      if (p && typeof p.catch === 'function') { p.catch(function () {}); }
    }

    function setPlayingUI(on) {
      playBtn.innerHTML = on ? '&#10074;&#10074;' : '&#9654;';
      playBtn.setAttribute('aria-label', on ? 'Pause' : 'Play');
      root.classList.toggle('is-playing', on);
    }

    playBtn.addEventListener('click', function () {
      // Build/resume the audio graph on this user gesture (browser policy).
      setupViz();
      if (!vizRAF) { drawViz(); }
      if (audio.paused) { play(); } else { audio.pause(); }
    });

    prevBtn.addEventListener('click', function () {
      // Restart current if we're past a few seconds, else go to previous.
      if (audio.currentTime > 3) { audio.currentTime = 0; }
      else { load(current - 1, true); }
    });

    nextBtn.addEventListener('click', function () { load(current + 1, true); });

    items.forEach(function (el, i) {
      el.addEventListener('click', function () {
        setupViz();
        if (!vizRAF) { drawViz(); }
        if (i === current && !audio.paused) { audio.pause(); }
        else { load(i, true); }
      });
    });

    // Match the canvas backing store to its display size (crisp on HiDPI).
    function sizeCanvas() {
      if (!canvas) { return; }
      var r = canvas.getBoundingClientRect();
      var dpr = Math.min(window.devicePixelRatio || 1, 2);
      canvas.width = Math.max(1, Math.round(r.width * dpr));
      canvas.height = Math.max(1, Math.round(r.height * dpr));
    }
    sizeCanvas();
    window.addEventListener('resize', sizeCanvas);

    // ---- Persistence (localStorage): track, time, volume, drawer ----
    var STORE = 'deadamp:v1';
    function saveState(extra) {
      try {
        var s = {
          src: audio.getAttribute('src') || (tracks(current) && tracks(current).getAttribute('data-src')),
          time: audio.currentTime || 0,
          volume: audio.volume,
          drawer: !!(document.getElementById('media-deck') && document.getElementById('media-deck').classList.contains('is-open'))
        };
        if (extra) { for (var k in extra) { s[k] = extra[k]; } }
        localStorage.setItem(STORE, JSON.stringify(s));
      } catch (e) {}
    }
    function loadState() {
      try { return JSON.parse(localStorage.getItem(STORE)) || {}; } catch (e) { return {}; }
    }
    var saved = loadState();

    // ---- Volume control ----
    var vol = document.getElementById('mp-vol');
    var volIcon = document.getElementById('mp-vol-icon');
    function applyVolumeUI() {
      if (volIcon) {
        volIcon.innerHTML = audio.volume === 0 ? '&#128263;' /*muted*/
          : (audio.volume < 0.5 ? '&#128265;' /*low*/ : '&#128266;' /*high*/);
      }
    }
    if (vol) {
      vol.addEventListener('input', function () {
        audio.volume = Math.min(1, Math.max(0, parseInt(vol.value, 10) / 100));
        applyVolumeUI();
        saveState();
      });
    }

    audio.addEventListener('play', function () { setPlayingUI(true); });
    audio.addEventListener('pause', function () { setPlayingUI(false); });
    audio.addEventListener('ended', function () { load(current + 1, true); });

    audio.addEventListener('loadedmetadata', function () {
      durEl.textContent = fmt(audio.duration);
    });

    var lastSave = 0;
    audio.addEventListener('timeupdate', function () {
      if (seeking) { return; }
      curEl.textContent = fmt(audio.currentTime);
      if (audio.duration) {
        seek.value = String((audio.currentTime / audio.duration) * 100);
      }
      // Persist position at most every ~3s so we can resume where they left off.
      var now = Date.now();
      if (now - lastSave > 3000) { lastSave = now; saveState(); }
    });
    audio.addEventListener('pause', function () { saveState(); });

    seek.addEventListener('input', function () { seeking = true; });
    seek.addEventListener('change', function () {
      if (audio.duration) {
        audio.currentTime = (parseFloat(seek.value) / 100) * audio.duration;
      }
      seeking = false;
      saveState();
    });
    // Save the final position when leaving the page.
    window.addEventListener('pagehide', function () { saveState(); });
    window.addEventListener('beforeunload', function () { saveState(); });

    // ---- Playlist drawer toggle ----
    var deck = document.getElementById('media-deck');
    var handle = document.getElementById('mp-handle');
    var drawer = document.getElementById('mp-drawer');
    var playerEl = document.getElementById('media-player');
    if (deck && handle && drawer && playerEl) {
      // Pin the drawer's height to the player's so a long list scrolls inside
      // the drawer rather than stretching it taller than the player.
      function syncDrawerHeight() {
        deck.style.setProperty('--mp-h', playerEl.offsetHeight + 'px');
      }
      syncDrawerHeight();
      window.addEventListener('resize', syncDrawerHeight);

      function setDrawer(open) {
        deck.classList.toggle('is-open', open);
        handle.setAttribute('aria-expanded', open ? 'true' : 'false');
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
      }
      handle.addEventListener('click', function () {
        syncDrawerHeight();
        setDrawer(!deck.classList.contains('is-open'));
        saveState();
      });
      // Restore drawer open state.
      if (saved.drawer) { syncDrawerHeight(); setDrawer(true); }
    }

    // ---- Restore saved state (track, position, volume) ----
    // Volume first (independent of track).
    if (typeof saved.volume === 'number') {
      audio.volume = Math.min(1, Math.max(0, saved.volume));
      if (vol) { vol.value = String(Math.round(audio.volume * 100)); }
    }
    applyVolumeUI();

    // Restore the last track by matching its src; seek to the saved time once
    // the audio is ready. Never autoplays (browser policy) — user presses play.
    if (saved.src) {
      var idx = -1;
      for (var j = 0; j < items.length; j++) {
        if (items[j].getAttribute('data-src') === saved.src) { idx = j; break; }
      }
      if (idx >= 0) {
        current = idx;
        audio.src = items[idx].getAttribute('data-src');
        titleEl.textContent = items[idx].getAttribute('data-title') || '';
        if (typeof saved.time === 'number' && saved.time > 0) {
          var seekOnce = function () {
            try { audio.currentTime = saved.time; } catch (e) {}
            curEl.textContent = fmt(audio.currentTime);
            if (audio.duration) { seek.value = String((audio.currentTime / audio.duration) * 100); }
            audio.removeEventListener('loadedmetadata', seekOnce);
          };
          audio.addEventListener('loadedmetadata', seekOnce);
        }
      }
    }

    markCurrent();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

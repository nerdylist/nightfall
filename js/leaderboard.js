/* THE DEAD LAST — Leaderboard page: live season countdown.
 *
 * The clock counts down to the season end moment (midnight after the end day),
 * synced to the SERVER clock — not the local device clock — so a competitive
 * timer can't be gamed by a wrong system time. We take the server/browser
 * offset once at load and tick locally from there.
 *
 * Data comes from #lb-clock:
 *   data-ends-unix    — season end moment, unix seconds (blank if no season)
 *   data-server-unix  — server "now" at render time, unix seconds
 * At zero the ticker freezes on "SEASON ENDED".
 */
(function () {
  "use strict";

  var el = document.getElementById("lb-clock");
  if (!el) return;

  var valueEl = document.getElementById("lb-clock-value");
  if (!valueEl) return;

  var endsUnix = parseInt(el.getAttribute("data-ends-unix"), 10);
  var serverUnix = parseInt(el.getAttribute("data-server-unix"), 10);

  // No configured season end — leave the dash the server rendered.
  if (!endsUnix || !serverUnix) return;

  // Offset between the server clock and this device's clock, captured once.
  // serverNow = local(now) + offset.
  var offsetMs = serverUnix * 1000 - Date.now();

  function pad(n) {
    return n < 10 ? "0" + n : String(n);
  }

  function render() {
    var serverNowMs = Date.now() + offsetMs;
    var remainingMs = endsUnix * 1000 - serverNowMs;

    if (remainingMs <= 0) {
      valueEl.textContent = "SEASON ENDED";
      valueEl.classList.add("is-ended");
      window.clearInterval(timer);
      return;
    }

    var totalSec = Math.floor(remainingMs / 1000);
    var days = Math.floor(totalSec / 86400);
    var hours = Math.floor((totalSec % 86400) / 3600);
    var mins = Math.floor((totalSec % 3600) / 60);
    var secs = totalSec % 60;

    // Show days only while there are some; otherwise HH:MM:SS.
    if (days > 0) {
      valueEl.textContent =
        days + "d " + pad(hours) + ":" + pad(mins) + ":" + pad(secs);
    } else {
      valueEl.textContent = pad(hours) + ":" + pad(mins) + ":" + pad(secs);
    }
  }

  render();
  var timer = window.setInterval(render, 1000);
})();

/* SEASON BOARDS (2026-07-22): tab strip toggles the server-rendered board
 * panels. No fetch — every board's top 10 is already in the page. */
(function () {
  "use strict";

  var tabs = document.getElementById("lb-board-tabs");
  if (!tabs) return;

  tabs.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-board]");
    if (!btn) return;

    var key = btn.getAttribute("data-board");

    tabs.querySelectorAll(".lb-boards__tab").forEach(function (t) {
      t.classList.toggle("is-active", t === btn);
    });
    document.querySelectorAll("[data-board-panel]").forEach(function (p) {
      p.classList.toggle("is-active", p.getAttribute("data-board-panel") === key);
    });
  });
})();

/* SURVIVOR | USER | ALL toggle (2026-07-23): switches which identity view
 * shows inside every board panel. Server rendered all three. */
(function () {
  "use strict";

  var who = document.getElementById("lb-board-who");
  if (!who) return;

  function apply(key) {
    who.querySelectorAll(".lb-boards__whobtn").forEach(function (b) {
      b.classList.toggle("is-active", b.getAttribute("data-who") === key);
    });
    document.querySelectorAll("[data-who-view]").forEach(function (v) {
      v.classList.toggle("is-active", v.getAttribute("data-who-view") === key);
    });
  }

  // CATEGORY FILTER (Boss 2026-07-23): USER mode shows only user-scoped
  // boards, SURVIVOR only survivor-scoped ('both' appears in each); ALL
  // shows every board. If the active tab is filtered out, the first
  // visible tab takes over.
  function filterTabs(key) {
    var tabsWrap = document.getElementById("lb-board-tabs");
    if (!tabsWrap) return;

    var activeVisible = false;
    var firstVisible = null;

    tabsWrap.querySelectorAll(".lb-boards__tab").forEach(function (t) {
      var scope = t.getAttribute("data-scope") || "both";
      var show = key === "all" || scope === "both" || scope === key;
      t.style.display = show ? "" : "none";
      if (show && !firstVisible) firstVisible = t;
      if (show && t.classList.contains("is-active")) activeVisible = true;
    });

    if (!activeVisible && firstVisible) {
      firstVisible.click();
    }
  }

  who.addEventListener("click", function (e) {
    var btn = e.target.closest("[data-who]");
    if (btn) {
      var key = btn.getAttribute("data-who");
      apply(key);
      filterTabs(key);
    }
  });

  apply("survivor");
  filterTabs("survivor");
})();

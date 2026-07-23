<?php
/**
 * THE DEAD LAST — Leaderboard page.
 *
 * Survival-time leaderboard: the season TOP list (SUM of per-character
 * per-day active survival time over the season window) and a TODAY panel
 * (today's buckets), plus the live season countdown.
 *
 * Server-rendered from the same aggregation the public GET /api/leaderboard
 * exposes (rendered here directly against the DB so the page shows data with
 * JavaScript disabled). leaderboard.js only drives the live countdown ticker,
 * syncing to the SERVER clock via the season fields embedded below.
 */

require_once __DIR__ . '/config.php';

const LEADERBOARD_PAGE_LIMIT = 25;

/** Read one host setting (settings table) or null. */
function lb_setting(PDO $db, string $key): ?string
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return ($value === false) ? null : (string) $value;
}

/** True when $value is a real calendar date in YYYY-MM-DD form. */
function lb_valid_date(?string $value): bool
{
    if ($value === null || $value === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

/** "41h 12m" from a whole number of seconds (">0" guaranteed by the query). */
function lb_format_duration(int $seconds): string
{
    $hours = intdiv($seconds, 3600);
    $mins  = intdiv($seconds % 3600, 60);

    if ($hours > 0) {
        return $hours . 'h ' . $mins . 'm';
    }
    if ($mins > 0) {
        return $mins . 'm';
    }
    return $seconds . 's';
}

/** A player-facing display name for a character (name, else its skin). */
function lb_character_name(array $row): string
{
    $name = trim((string) ($row['name'] ?? ''));

    return $name !== '' ? $name : (string) $row['skin'];
}

$db = grave_db();

$seasonStart = lb_setting($db, 'season_start');
$seasonEnd   = lb_setting($db, 'season_end');
$hasWindow   = lb_valid_date($seasonStart) && lb_valid_date($seasonEnd);

$now        = new DateTime();
$serverDate = $now->format('Y-m-d');

// Season end moment (midnight after the end day) for the countdown target.
$endsAtUnix = null;
if (lb_valid_date($seasonEnd)) {
    $endDt = DateTime::createFromFormat('Y-m-d H:i:s', $seasonEnd . ' 00:00:00');
    if ($endDt instanceof DateTime) {
        $endDt->modify('+1 day');
        $endsAtUnix = $endDt->getTimestamp();
    }
}

// ---- TOP: SUM(seconds) per character over the season window (or all-time). ----
if ($hasWindow) {
    $topStmt = $db->prepare(
        'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
                agg.seconds AS seconds
         FROM (
             SELECT character_id, SUM(seconds) AS seconds
             FROM character_playtime
             WHERE date BETWEEN :start AND :end
             GROUP BY character_id
         ) agg
         JOIN characters c ON c.id = agg.character_id
         JOIN users u      ON u.id = c.user_id
         WHERE agg.seconds > 0
         ORDER BY agg.seconds DESC, c.id ASC
         LIMIT ' . LEADERBOARD_PAGE_LIMIT
    );
    $topStmt->execute(['start' => $seasonStart, 'end' => $seasonEnd]);
} else {
    $topStmt = $db->query(
        'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
                agg.seconds AS seconds
         FROM (
             SELECT character_id, SUM(seconds) AS seconds
             FROM character_playtime
             GROUP BY character_id
         ) agg
         JOIN characters c ON c.id = agg.character_id
         JOIN users u      ON u.id = c.user_id
         WHERE agg.seconds > 0
         ORDER BY agg.seconds DESC, c.id ASC
         LIMIT ' . LEADERBOARD_PAGE_LIMIT
    );
}
$topRows = $topStmt->fetchAll();

// ---- TODAY: each character's bucket for the current server date. ----
$todayStmt = $db->prepare(
    'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
            p.seconds AS seconds
     FROM character_playtime p
     JOIN characters c ON c.id = p.character_id
     JOIN users u      ON u.id = c.user_id
     WHERE p.date = :today AND p.seconds > 0
     ORDER BY p.seconds DESC, c.id ASC
     LIMIT ' . LEADERBOARD_PAGE_LIMIT
);
$todayStmt->execute(['today' => $serverDate]);
$todayRows = $todayStmt->fetchAll();

// ---- SEASON BOARDS (2026-07-22): per-user boards from player_stats. All
// rendered server-side (top 10 each); the tab strip just toggles panels, so
// the section works with JavaScript disabled (first board shown).
require_once __DIR__ . '/lib/boards.php';
$boardData = [];
foreach (TDL_BOARDS as $bKey => $bDef) {
    $boardData[$bKey] = tdl_board_rows($db, $bKey, 10);
}

$pageTitle = 'Leaderboard — The Dead Last';
$pageCss   = ['/css/leaderboard.css'];
$pageJs    = ['/js/leaderboard.js'];
include __DIR__ . '/partials/header.php';
?>
<main class="lb container">
  <header class="lb-head">
    <div class="lb-head__titles">
      <h1 class="lb-title">Leaderboard</h1>
      <p class="text-muted lb-subtitle">Most active survival time this season. Every character races the season clock &mdash; and permadeath makes the climb count.</p>
    </div>

    <div class="lb-clock" id="lb-clock"
         data-ends-unix="<?= $endsAtUnix !== null ? (int) $endsAtUnix : '' ?>"
         data-server-unix="<?= (int) $now->getTimestamp() ?>">
      <span class="lb-clock__label">Season Ends In</span>
      <span class="lb-clock__value" id="lb-clock-value">
        <?php if ($endsAtUnix === null): ?>&mdash;<?php else: ?>&hellip;<?php endif; ?>
      </span>
      <?php if ($hasWindow): ?>
        <span class="lb-clock__window text-muted">
          <?= htmlspecialchars($seasonStart) ?> &rarr; <?= htmlspecialchars($seasonEnd) ?>
        </span>
      <?php else: ?>
        <span class="lb-clock__window text-muted">No active season &mdash; all-time standings</span>
      <?php endif; ?>
    </div>
  </header>

  <div class="lb-grid">
    <!-- SEASON TOP -->
    <section class="lb-panel lb-panel--top">
      <div class="lb-panel__head">
        <h2 class="lb-panel__title">Season Top</h2>
        <span class="lb-panel__hint text-muted">Total survival time</span>
      </div>

      <?php if (empty($topRows)): ?>
        <p class="lb-empty text-muted">No survival time logged yet this season. Be the first legend.</p>
      <?php else: ?>
        <ol class="lb-list">
          <?php foreach ($topRows as $i => $row): $rank = $i + 1; ?>
            <li class="lb-row<?= $rank <= 3 ? ' lb-row--podium lb-row--r' . $rank : '' ?>">
              <span class="lb-row__rank"><?= $rank ?></span>
              <span class="lb-row__who">
                <span class="lb-row__char"><?= htmlspecialchars(lb_character_name($row)) ?></span>
                <span class="lb-row__meta text-muted">
                  <span class="lb-row__skin"><?= htmlspecialchars((string) $row['skin']) ?></span>
                  <span class="lb-row__user">@<?= htmlspecialchars((string) $row['username']) ?></span>
                  <?php if (!empty($row['outcome'])): ?>
                    <span class="lb-row__outcome" title="Character ended: <?= htmlspecialchars((string) $row['outcome']) ?>">&dagger; <?= htmlspecialchars((string) $row['outcome']) ?></span>
                  <?php endif; ?>
                </span>
              </span>
              <span class="lb-row__time"><?= htmlspecialchars(lb_format_duration((int) $row['seconds'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <!-- TODAY -->
    <aside class="lb-panel lb-panel--today">
      <div class="lb-panel__head">
        <h2 class="lb-panel__title">Today</h2>
        <span class="lb-panel__hint text-muted"><?= htmlspecialchars($serverDate) ?></span>
      </div>

      <?php if (empty($todayRows)): ?>
        <p class="lb-empty text-muted">Nobody has logged time today.</p>
      <?php else: ?>
        <ol class="lb-list lb-list--today">
          <?php foreach ($todayRows as $i => $row): $rank = $i + 1; ?>
            <li class="lb-row lb-row--sm">
              <span class="lb-row__rank"><?= $rank ?></span>
              <span class="lb-row__who">
                <span class="lb-row__char"><?= htmlspecialchars(lb_character_name($row)) ?></span>
                <span class="lb-row__meta text-muted">
                  <span class="lb-row__user">@<?= htmlspecialchars((string) $row['username']) ?></span>
                </span>
              </span>
              <span class="lb-row__time"><?= htmlspecialchars(lb_format_duration((int) $row['seconds'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </aside>
  </div>

  <!-- SEASON BOARDS -->
  <section class="lb-boards">
    <div class="lb-panel__head">
      <h2 class="lb-panel__title">Season Boards</h2>
      <span class="lb-panel__hint text-muted">Every way to be remembered</span>
    </div>

    <div class="lb-boards__tabs" id="lb-board-tabs">
      <?php $first = true; foreach (TDL_BOARDS as $bKey => $bDef): ?>
        <button type="button"
                class="lb-boards__tab<?= $first ? ' is-active' : '' ?>"
                data-board="<?= htmlspecialchars($bKey) ?>"
                title="<?= htmlspecialchars($bDef['blurb']) ?>">
          <img class="lb-boards__icon"
               src="<?= htmlspecialchars($bDef['icon']) ?>"
               alt="<?= htmlspecialchars($bDef['label']) ?>" loading="lazy">
          <span class="lb-boards__tablabel"><?= htmlspecialchars($bDef['label']) ?></span>
        </button>
      <?php $first = false; endforeach; ?>
    </div>

    <?php $first = true; foreach (TDL_BOARDS as $bKey => $bDef): ?>
      <div class="lb-boards__panel<?= $first ? ' is-active' : '' ?>"
           data-board-panel="<?= htmlspecialchars($bKey) ?>">
        <p class="lb-boards__blurb text-muted"><?= htmlspecialchars($bDef['blurb']) ?></p>
        <?php if (empty($boardData[$bKey])): ?>
          <p class="lb-empty text-muted">Nobody on this board yet. History awaits.</p>
        <?php else: ?>
          <ol class="lb-list lb-list--board">
            <?php foreach ($boardData[$bKey] as $i => $row): $rank = $i + 1; ?>
              <li class="lb-row lb-row--sm<?= $rank === 1 ? ' lb-row--podium lb-row--r1' : '' ?>">
                <span class="lb-row__rank"><?= $rank ?></span>
                <span class="lb-row__who">
                  <span class="lb-row__char">@<?= htmlspecialchars((string) $row['username']) ?></span>
                </span>
                <span class="lb-row__time"><?= htmlspecialchars(tdl_board_format($bDef, (int) $row['value'])) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>
      </div>
    <?php $first = false; endforeach; ?>
  </section>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php
/**
 * THE DEAD LAST — public user profile: /u/{username}
 *
 * A full survivor profile: identity + last survivor played (art matched from
 * the Characters roster by skin), headline game stats (characters played, true
 * deaths, playtime, kills, distance, bank), an Achievements grid built from the
 * titled leaderboard stats (earned = value > 0, with the value shown), and
 * Recent Activity merged from the forum.
 *
 * HONEST DATA ONLY: unknown username = real 404.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/boards.php';        // TDL_BOARDS + tdl_board_format()
require_once __DIR__ . '/bbs/db.php';            // forum_db() — single userbase
require_once __DIR__ . '/bbs/partials/avatar.php';

// friendly-URL: /u/:user
if (isset($GLOBALS['_ROUTE_PARAMS']['user']) && !isset($_GET['user'])) {
    $_GET['user'] = $GLOBALS['_ROUTE_PARAMS']['user'];
}
$requested = trim((string) ($_GET['user'] ?? ''));

$db = grave_db();

$profile = null;
if ($requested !== '') {
    $stmt = $db->prepare(
        'SELECT id, username, role, status, created_at FROM users WHERE username = :u COLLATE NOCASE'
    );
    $stmt->execute(['u' => $requested]);
    $profile = $stmt->fetch();
}

if (!$profile || ($profile['status'] ?? '') === 'banned') {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

$uid = (int) $profile['id'];
$fdb = forum_db();

// ---- Game stats (user aggregate). Missing row = all zeros. ----
$psStmt = $db->prepare('SELECT * FROM player_stats WHERE user_id = ?');
$psStmt->execute([$uid]);
$ps = $psStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$stat = static fn (string $k): int => (int) ($ps[$k] ?? 0);

// ---- Survivors: how many lives run, and the most recent one (for art). ----
$survCount = 0;
$lastSurvivor = null;
try {
    $sc = $db->prepare('SELECT COUNT(*) FROM survivors WHERE user_id = ?');
    $sc->execute([$uid]);
    $survCount = (int) $sc->fetchColumn();

    $ls = $db->prepare(
        'SELECT name, skin, outcome, started_at, ended_at
         FROM survivors WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    );
    $ls->execute([$uid]);
    $lastSurvivor = $ls->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    // survivors table may not exist on an un-migrated DB — degrade gracefully.
}

// Match the last survivor's skin to an authored Character for its avatar art.
$survivorArt = null;
$survivorLabel = null;
if ($lastSurvivor) {
    $survivorLabel = trim((string) ($lastSurvivor['name'] ?: $lastSurvivor['skin']));
    $needle = trim((string) ($lastSurvivor['skin'] ?: $lastSurvivor['name']));
    if ($needle !== '') {
        try {
            $cs = $db->prepare(
                'SELECT avatar_path, pose_path FROM characters
                 WHERE name = :n COLLATE NOCASE OR name = :s COLLATE NOCASE
                 LIMIT 1'
            );
            $cs->execute([':n' => $needle, ':s' => (string) $lastSurvivor['name']]);
            $row = $cs->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $survivorArt = $row['avatar_path'] ?: ($row['pose_path'] ?: null);
            }
        } catch (Throwable $e) {
            // characters table may not exist yet — no art, that's fine.
        }
    }
}

// ---- Achievements: titled leaderboard stats the user has any progress in. ----
$achievements = [];
foreach (TDL_BOARDS as $key => $def) {
    $col = $def['column'];
    if (!array_key_exists($col, $ps)) {
        continue; // column not on this DB
    }
    $val = (int) $ps[$col];
    if ($val <= 0) {
        continue; // earned = value > 0
    }
    $achievements[] = [
        'label' => $def['label'],
        'blurb' => $def['blurb'],
        'icon'  => $def['icon'],
        'value' => tdl_board_format($def, $val),
        'raw'   => $val,
    ];
}
// Strongest first-ish: keep the board declaration order (curated), but float
// the highest raw counts up a little so a profile leads with its best.
usort($achievements, fn ($a, $b) => $b['raw'] <=> $a['raw']);

// ---- Forum stats + recent activity. ----
$tq = $fdb->prepare('SELECT COUNT(*) FROM threads WHERE author_id = ?');
$tq->execute([$uid]);
$threadCount = (int) $tq->fetchColumn();

$cq = $fdb->prepare('SELECT COUNT(*) FROM chat_messages WHERE author_id = ?');
$cq->execute([$uid]);
$commentCount = (int) $cq->fetchColumn();

$aq = $fdb->prepare(
    "SELECT 'thread' AS kind, t.id AS thread_id, t.title AS title, NULL AS text,
            t.created_at AS at
       FROM threads t WHERE t.author_id = :u
     UNION ALL
     SELECT 'comment' AS kind, m.thread_id AS thread_id,
            (SELECT title FROM threads t2 WHERE t2.id = m.thread_id) AS title,
            m.text AS text, m.created_at AS at
       FROM chat_messages m WHERE m.author_id = :u
     ORDER BY at DESC
     LIMIT 12"
);
$aq->execute(['u' => $uid]);
$activity = $aq->fetchAll(PDO::FETCH_ASSOC);

// ---- Helpers ----
function u_time_ago(?string $ts): string
{
    $t = $ts !== null ? strtotime($ts) : false;
    if ($t === false) { return ''; }
    $d = time() - $t;
    if ($d < 60) { return 'just now'; }
    if ($d < 3600) { return floor($d / 60) . 'm ago'; }
    if ($d < 86400) { return floor($d / 3600) . 'h ago'; }
    if ($d < 604800) { return floor($d / 86400) . 'd ago'; }
    return date('M j, Y', $t);
}

function u_duration(int $seconds): string
{
    if ($seconds <= 0) { return '0h'; }
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h >= 1) { return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : ''); }
    return $m . 'm';
}

$joined = $profile['created_at'] ? date('F j, Y', strtotime($profile['created_at'])) : '—';
$role = (string) ($profile['role'] ?? 'survivor');
$roleLabel = $role === 'admin' ? 'Keeper' : ($role === 'moderator' ? 'Moderator' : 'Survivor');

$totalKills = $stat('humans_killed') + $stat('zombies_killed')
    + $stat('kills_hvz') + $stat('kills_hvh') + $stat('kills_zvz') + $stat('kills_zvh');

// Headline stat band. Only show playtime/kills/etc. that mean something.
$headline = [
    ['label' => 'Characters', 'value' => number_format(max($survCount, $stat('lives')))],
    ['label' => 'True Deaths', 'value' => number_format($stat('true_deaths'))],
    ['label' => 'Kills',       'value' => number_format($totalKills)],
    ['label' => 'Playtime',    'value' => u_duration($stat('playtime_seconds'))],
    ['label' => 'Distance',    'value' => $stat('distance_m') >= 1000 ? number_format($stat('distance_m') / 1000, 1) . ' km' : $stat('distance_m') . ' m'],
    ['label' => 'Bank',        'value' => '$' . number_format($stat('bank'))],
];

$hasGameData = ($survCount > 0) || !empty($ps);

$pageTitle = strtoupper($profile['username']) . ' — The Dead Last';
$pageCss = ['/css/profile-page.css'];
include __DIR__ . '/partials/header.php';
?>
<main class="container uprof">

  <header class="uprof-hero">
    <div class="uprof-hero__identity">
      <div class="uprof-avatar"><?php render_avatar($profile['username'], 112); ?></div>
      <div class="uprof-id">
        <h1 class="uprof-name"><?= htmlspecialchars(strtoupper($profile['username'])) ?></h1>
        <div class="uprof-meta">
          <span class="uprof-role uprof-role--<?= htmlspecialchars($role) ?>"><?= $roleLabel ?></span>
          <span class="uprof-joined">Joined <?= htmlspecialchars($joined) ?></span>
        </div>
      </div>
    </div>

    <?php if ($lastSurvivor): ?>
    <div class="uprof-lastsurv" title="Most recent survivor">
      <div class="uprof-lastsurv__art">
        <?php if ($survivorArt): ?>
          <img src="/<?= htmlspecialchars(ltrim((string) $survivorArt, '/')) ?>" alt="" loading="lazy" onerror="this.style.display='none'">
        <?php else: ?>
          <span class="uprof-lastsurv__noart"><?= htmlspecialchars(strtoupper(substr((string) $survivorLabel, 0, 1))) ?></span>
        <?php endif; ?>
      </div>
      <div class="uprof-lastsurv__info">
        <span class="uprof-lastsurv__kicker">Last Survivor</span>
        <span class="uprof-lastsurv__name"><?= htmlspecialchars($survivorLabel ?: '—') ?></span>
        <?php if (!empty($lastSurvivor['outcome'])): ?>
          <span class="uprof-lastsurv__outcome"><?= htmlspecialchars(str_replace('_', ' ', (string) $lastSurvivor['outcome'])) ?></span>
        <?php elseif (empty($lastSurvivor['ended_at'])): ?>
          <span class="uprof-lastsurv__outcome uprof-lastsurv__outcome--alive">alive</span>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </header>

  <?php if ($hasGameData): ?>
  <section class="uprof-band" aria-label="Stats">
    <?php foreach ($headline as $h): ?>
      <div class="uprof-band__stat">
        <span class="uprof-band__value"><?= htmlspecialchars($h['value']) ?></span>
        <span class="uprof-band__label"><?= htmlspecialchars($h['label']) ?></span>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <div class="uprof-grid">

    <section class="uprof-panel uprof-achievements" aria-label="Achievements">
      <div class="uprof-section-head">
        <h2>Achievements</h2>
        <?php if ($achievements): ?><span class="uprof-count"><?= count($achievements) ?></span><?php endif; ?>
      </div>
      <?php if (empty($achievements)): ?>
        <p class="uprof-empty">No titles earned yet. Survive, and they will come.</p>
      <?php else: ?>
        <ul class="uprof-achv-grid">
          <?php foreach ($achievements as $a): ?>
            <li class="uprof-achv" title="<?= htmlspecialchars($a['blurb']) ?>">
              <span class="uprof-achv__icon"><img src="<?= htmlspecialchars($a['icon']) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'"></span>
              <span class="uprof-achv__label"><?= htmlspecialchars($a['label']) ?></span>
              <span class="uprof-achv__value"><?= htmlspecialchars($a['value']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="uprof-panel uprof-activity" aria-label="Recent activity">
      <div class="uprof-section-head">
        <h2>Recent Activity</h2>
        <div class="uprof-forumcounts">
          <span><?= number_format($threadCount) ?> threads</span>
          <span><?= number_format($commentCount) ?> comments</span>
        </div>
      </div>

      <?php if (empty($activity)): ?>
        <p class="uprof-empty">Nothing yet. The night is young.</p>
      <?php else: ?>
        <ul class="uprof-feed">
          <?php foreach ($activity as $item): ?>
            <li class="uprof-item uprof-item--<?= htmlspecialchars($item['kind']) ?>">
              <span class="uprof-kind"><?= $item['kind'] === 'thread' ? 'Started a thread' : 'Commented' ?></span>
              <a class="uprof-item-title" href="/bbs/thread/<?= (int) $item['thread_id'] ?>">
                <?= htmlspecialchars($item['title'] ?? 'Untitled') ?>
              </a>
              <?php if ($item['kind'] === 'comment' && $item['text'] !== null): ?>
                <span class="uprof-excerpt">&ldquo;<?= htmlspecialchars(mb_strimwidth((string) $item['text'], 0, 120, '…')) ?>&rdquo;</span>
              <?php endif; ?>
              <span class="uprof-when"><?= htmlspecialchars(u_time_ago($item['at'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

  </div>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

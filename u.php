<?php
/**
 * THE DEAD LAST — public user profile: /u/{username}
 *
 * Basic identity card (avatar, username, role badge, joined) + forum stats
 * (threads / comments, live-counted) + RECENT ACTIVITY merged from the forum:
 * threads started and live-chat comments, newest first. Links through to the
 * fuller forum profile at /bbs/profile/{username}.
 *
 * HONEST DATA ONLY: unknown username = real 404 (no fall-back-to-first-user
 * scaffolding like early bbs pages had).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/bbs/db.php';            // forum_db() — single userbase
require_once __DIR__ . '/bbs/partials/avatar.php';

// friendly-URL: /u/:user
if (isset($GLOBALS['_ROUTE_PARAMS']['user']) && !isset($_GET['user'])) {
    $_GET['user'] = $GLOBALS['_ROUTE_PARAMS']['user'];
}
$requested = trim((string) ($_GET['user'] ?? ''));

$profile = null;
if ($requested !== '') {
    $stmt = grave_db()->prepare(
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

// Live forum stats.
$tq = $fdb->prepare('SELECT COUNT(*) FROM threads WHERE author_id = ?');
$tq->execute([$uid]);
$threadCount = (int) $tq->fetchColumn();

$cq = $fdb->prepare('SELECT COUNT(*) FROM chat_messages WHERE author_id = ?');
$cq->execute([$uid]);
$commentCount = (int) $cq->fetchColumn();

// Recent activity: threads started + comments, merged newest-first.
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

// Relative timestamps ("just now" / "5m ago" / date).
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

$joined = $profile['created_at'] ? date('F j, Y', strtotime($profile['created_at'])) : '—';
$role = (string) ($profile['role'] ?? 'survivor');
$roleLabel = $role === 'admin' ? 'Keeper' : ($role === 'moderator' ? 'Moderator' : 'Survivor');

$pageTitle = strtoupper($profile['username']) . ' — The Dead Last';
$pageCss = ['/css/profile-page.css'];
include __DIR__ . '/partials/header.php';
?>
<main class="container uprof">
  <header class="uprof-head">
    <div class="uprof-avatar"><?php render_avatar($profile['username'], 96); ?></div>
    <div class="uprof-id">
      <h1 class="uprof-name"><?= htmlspecialchars(strtoupper($profile['username'])) ?></h1>
      <div class="uprof-meta">
        <span class="uprof-role uprof-role--<?= htmlspecialchars($role) ?>"><?= $roleLabel ?></span>
        <span class="uprof-joined">Joined <?= htmlspecialchars($joined) ?></span>
      </div>
    </div>
    <dl class="uprof-stats">
      <div class="uprof-stat"><dt>Threads</dt><dd><?= number_format($threadCount) ?></dd></div>
      <div class="uprof-stat"><dt>Comments</dt><dd><?= number_format($commentCount) ?></dd></div>
    </dl>
  </header>

  <section class="uprof-activity" aria-label="Recent activity">
    <div class="uprof-section-head">
      <h2>Recent Activity</h2>
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
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

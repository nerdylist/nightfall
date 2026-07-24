<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

/**
 * Keeper > Leaderboard — season data control (Boss 2026-07-23: "let me
 * control whatever data is on there... delete survivors/characters if need
 * be. Some of these are just for testing.").
 *
 * Two tables:
 *   SURVIVORS — every characters row with its playtime + key board stats.
 *     DELETE removes the character AND its leaderboard footprint
 *     (character_playtime buckets + character_stats row). The per-USER
 *     aggregates in player_stats are untouched by design — use the user
 *     table's ZERO BOARD STATS for those.
 *   USER BOARD STATS — per-user player_stats rows. ZERO wipes every board
 *     column back to 0 (test-data cleanup); the user account is untouched.
 */

if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

// Every stats column the ZERO action resets (mirrors the API whitelist,
// minus 'lives' which is registration history rather than a board).
const KEEPER_BOARD_COLUMNS = [
    'humans_killed', 'zombies_killed', 'times_turned', 'deaths', 'true_deaths',
    'redemptions', 'biggest_horde_size', 'longest_life_seconds',
    'playtime_seconds', 'bank',
    'kills_hvz', 'kills_hvh', 'kills_zvz', 'kills_zvh', 'bat_kills',
    'humans_infected', 'chests_looted', 'distance_m', 'banked_total',
    'hunter_pure_kills', 'allie_pure_kills', 'died_rich', 'insomniac_seconds',
    'long_walk_seconds', 'kill_free_life_seconds', 'lazarus_seconds',
    'fastest_death_seconds',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/leaderboard.php');
        exit;
    }

    $db = grave_db();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete_character' && $id > 0) {
        $stmt = $db->prepare('SELECT c.name, c.skin, u.username
                              FROM characters c JOIN users u ON u.id = c.user_id
                              WHERE c.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row) {
            $db->prepare('DELETE FROM character_playtime WHERE character_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM character_stats WHERE character_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM characters WHERE id = ?')->execute([$id]);
            $label = trim((string) ($row['name'] ?: $row['skin']));
            $_SESSION['keeper_flash'] = 'Survivor "' . $label . '" (@' . $row['username']
                . ') removed from the season books.';
        } else {
            $_SESSION['keeper_flash'] = 'Survivor not found.';
        }

        header('Location: /keeper/leaderboard.php');
        exit;
    }

    if ($action === 'zero_user_stats' && $id > 0) {
        $sets = implode(', ', array_map(fn ($c) => "{$c} = 0", KEEPER_BOARD_COLUMNS));
        $db->prepare("UPDATE player_stats SET {$sets}, updated_at = CURRENT_TIMESTAMP
                      WHERE user_id = ?")->execute([$id]);
        $_SESSION['keeper_flash'] = 'User board stats zeroed.';
        header('Location: /keeper/leaderboard.php');
        exit;
    }

    $_SESSION['keeper_flash'] = 'Unknown action.';
    header('Location: /keeper/leaderboard.php');
    exit;
}

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$keeperPageTitle = 'Leaderboard — Keeper';
$keeperCurrent = 'leaderboard.php';
include __DIR__ . '/../partials/keeper-header.php';

$db = grave_db();

$totalChars = (int) $db->query('SELECT COUNT(*) FROM characters')->fetchColumn();
$endedChars = (int) $db->query('SELECT COUNT(*) FROM characters WHERE ended_at IS NOT NULL')->fetchColumn();

// SURVIVORS with their leaderboard footprint.
$chars = $db->query(
    "SELECT c.id, c.name, c.skin, c.outcome, c.started_at, c.ended_at, u.username,
            COALESCE(pt.seconds, 0) AS playtime,
            COALESCE(cs.chests_looted, 0) AS chests,
            COALESCE(cs.kills_hvz + cs.kills_hvh + cs.kills_zvz + cs.kills_zvh, 0) AS kills,
            COALESCE(cs.distance_m, 0) AS distance
     FROM characters c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN (SELECT character_id, SUM(seconds) AS seconds
                FROM character_playtime GROUP BY character_id) pt
            ON pt.character_id = c.id
     LEFT JOIN character_stats cs ON cs.character_id = c.id
     ORDER BY playtime DESC, c.id ASC"
)->fetchAll();

// USER board rows.
$userStats = $db->query(
    "SELECT u.id, u.username, s.playtime_seconds, s.bank, s.true_deaths,
            s.chests_looted, s.distance_m, s.insomniac_seconds
     FROM player_stats s JOIN users u ON u.id = s.user_id
     ORDER BY s.playtime_seconds DESC"
)->fetchAll();

function keeper_lb_duration(int $seconds): string
{
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($h > 0) { return $h . 'h ' . $m . 'm'; }
    if ($m > 0) { return $m . 'm'; }
    return $seconds . 's';
}
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Leaderboard</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Survivors On The Books</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalChars) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Ended</p>
        <p class="keeper-stat-tile__value"><?= number_format($endedChars) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Users With Stats</p>
        <p class="keeper-stat-tile__value"><?= number_format(count($userStats)) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Survivors</h2>
      <p class="text-muted" style="margin-top:0">DELETE removes the survivor and every trace of them from the season boards (playtime buckets + survivor stats). User aggregates are separate — zero those below.</p>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>ID</th><th>Survivor</th><th>Skin</th><th>User</th>
              <th>Playtime</th><th>Kills</th><th>Chests</th><th>Distance</th>
              <th>Outcome</th><th>Started</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($chars as $c): ?>
            <tr>
              <td class="keeper-cell-num"><?= (int) $c['id'] ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars(trim((string) ($c['name'] ?: $c['skin']))) ?>"><?= htmlspecialchars(trim((string) ($c['name'] ?: $c['skin']))) ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars((string) $c['skin']) ?>"><?= htmlspecialchars((string) $c['skin']) ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="@<?= htmlspecialchars((string) $c['username']) ?>">@<?= htmlspecialchars((string) $c['username']) ?></td>
              <td class="keeper-cell-num"><?= htmlspecialchars(keeper_lb_duration((int) $c['playtime'])) ?></td>
              <td class="keeper-cell-num"><?= (int) $c['kills'] ?></td>
              <td class="keeper-cell-num"><?= (int) $c['chests'] ?></td>
              <td class="keeper-cell-num"><?= number_format((int) $c['distance']) ?> m</td>
              <td class="keeper-cell-nowrap"><?= htmlspecialchars((string) ($c['outcome'] ?: ($c['ended_at'] ? 'ended' : 'alive'))) ?></td>
              <td class="keeper-cell-nowrap"><?= htmlspecialchars(substr((string) $c['started_at'], 0, 10)) ?></td>
              <td>
                <div class="keeper-row-actions">
                  <form method="post" onsubmit="return confirm('Remove this survivor and all their leaderboard data? This cannot be undone.');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete_character">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="keeper-icon-btn keeper-icon-btn--danger" title="Delete survivor" aria-label="Delete">
                      <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash.svg" alt="">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">User Board Stats</h2>
      <p class="text-muted" style="margin-top:0">ZERO wipes every board column for the account (playtime, kills, money boards, records). The account itself is untouched.</p>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>User</th><th>Playtime</th><th>Bank</th><th>True Deaths</th>
              <th>Chests</th><th>Distance</th><th>Insomniac</th><th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($userStats as $u): ?>
            <tr>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="@<?= htmlspecialchars((string) $u['username']) ?>">@<?= htmlspecialchars((string) $u['username']) ?></td>
              <td class="keeper-cell-num"><?= htmlspecialchars(keeper_lb_duration((int) $u['playtime_seconds'])) ?></td>
              <td class="keeper-cell-num">$<?= number_format((int) $u['bank']) ?></td>
              <td class="keeper-cell-num"><?= (int) $u['true_deaths'] ?></td>
              <td class="keeper-cell-num"><?= (int) $u['chests_looted'] ?></td>
              <td class="keeper-cell-num"><?= number_format((int) $u['distance_m']) ?> m</td>
              <td class="keeper-cell-num"><?= htmlspecialchars(keeper_lb_duration((int) $u['insomniac_seconds'])) ?></td>
              <td>
                <div class="keeper-row-actions">
                  <form method="post" onsubmit="return confirm('Zero every board stat for this user? This cannot be undone.');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="zero_user_stats">
                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                    <button type="submit" class="keeper-icon-btn keeper-icon-btn--danger" title="Zero all board stats" aria-label="Zero stats">
                      <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/eraser.svg" alt="">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

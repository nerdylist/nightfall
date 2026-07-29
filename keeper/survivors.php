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
 * Keeper > Game Data > Survivors — admin god-mode CRUD over EVERY survivor
 * (one player life in the `survivors` table: game-spawned, permadeath) across
 * all users. This is distinct from the authored `characters` roster
 * (keeper/characters.php) — different table, different feature.
 *
 * The survivor sheet rules mirror api/stats/index.php (the game ingest). The
 * two XP-curve helpers below are COPIED VERBATIM from there and MUST STAY IN
 * SYNC — including that file here would run its request handling, so we don't.
 */

/**
 * The eight survivor attributes. Order matches api/stats/index.php's
 * SURVIVOR_ATTRS. `str`/`end`/`int` are SQLite reserved words — every SQL
 * statement quotes them as "str"/"end"/"int".
 */
const KEEPER_SURVIVOR_ATTRS = ['str', 'agi', 'end', 'int', 'awa', 'luk', 'foc', 'fai'];
/** Starting allocation pool (base 0 at create; buys the first 14 points free). */
const KEEPER_SURVIVOR_START_POOL = 14;
/** Per-attribute cap from allocation. */
const KEEPER_SURVIVOR_ATTR_CAP = 10;

/**
 * XP cost to buy the nth stat point. COPIED VERBATIM from
 * api/stats/index.php::survivor_point_cost() — keep in sync (see that file's
 * doc for the curve rationale). Validation only, not stored.
 */
function survivor_point_cost(int $n): int
{
    $k = $n - 1;
    $raw = 4000 + 600 * $k + intdiv(323 * $k * $k, 10);
    return intdiv($raw + 50, 100) * 100; // round half-up to nearest 100
}

/**
 * Points earned from cumulative XP. COPIED VERBATIM from
 * api/stats/index.php::survivor_points_earned() — keep in sync.
 */
function survivor_points_earned(int $xp): int
{
    if ($xp <= 0) {
        return 0;
    }
    $earned = 0;
    $spent = 0;
    for ($n = 1; ; $n++) {
        $cost = survivor_point_cost($n);
        if ($spent + $cost > $xp) {
            break;
        }
        $spent += $cost;
        $earned = $n;
        if ($n > 100000) { // hard stop, far beyond max (66)
            break;
        }
    }
    return $earned;
}

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/survivors.php');
        exit;
    }

    $db = grave_db();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $db->prepare('SELECT id FROM survivors WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        $_SESSION['keeper_flash'] = 'Survivor not found.';
        header('Location: /keeper/survivors.php');
        exit;
    }

    // --- Edit a survivor's game-data sheet ---
    if ($action === 'edit_survivor') {
        $name    = trim((string) ($_POST['name'] ?? ''));
        $outcome = trim((string) ($_POST['outcome'] ?? ''));
        $skin    = trim((string) ($_POST['skin'] ?? ''));
        $xp      = (int) ($_POST['xp'] ?? 0);
        $pointsSpent = (int) ($_POST['points_spent'] ?? 0);

        $errors = [];

        // xp >= 0.
        if ($xp < 0) {
            $errors[] = 'XP must be zero or greater.';
        }
        // points_spent >= 0.
        if ($pointsSpent < 0) {
            $errors[] = 'Points spent must be zero or greater.';
        }

        // The eight attributes: each an integer 0..cap.
        $attrs = [];
        foreach (KEEPER_SURVIVOR_ATTRS as $a) {
            $v = (int) ($_POST[$a] ?? 0);
            if ($v < 0 || $v > KEEPER_SURVIVOR_ATTR_CAP) {
                $errors[] = strtoupper($a) . ' must be an integer 0..' . KEEPER_SURVIVOR_ATTR_CAP . '.';
            }
            $attrs[$a] = $v;
        }

        // Rule 1: sum of the eight attributes <= starting pool + points_spent.
        if (!$errors) {
            $attrSum = array_sum($attrs);
            if ($attrSum > KEEPER_SURVIVOR_START_POOL + $pointsSpent) {
                $errors[] = 'Attribute sum (' . $attrSum . ') exceeds allowed ('
                    . (KEEPER_SURVIVOR_START_POOL + $pointsSpent) . ' = '
                    . KEEPER_SURVIVOR_START_POOL . ' start + ' . $pointsSpent . ' spent).';
            }
        }

        // Rule 2: points_spent <= points the XP actually earns.
        if (!$errors) {
            $earned = survivor_points_earned($xp);
            if ($pointsSpent > $earned) {
                $errors[] = 'Points spent (' . $pointsSpent . ') exceeds points earned by XP ('
                    . $earned . ' at ' . $xp . ' XP).';
            }
        }

        if ($errors) {
            $_SESSION['keeper_flash'] = implode(' ', $errors);
            header('Location: /keeper/survivors.php');
            exit;
        }

        // Persist. Reserved-ish attribute column names are quoted.
        $sql = 'UPDATE survivors SET name = :name, outcome = :outcome, skin = :skin, '
             . '"str" = :str, "agi" = :agi, "end" = :end, "int" = :int, '
             . '"awa" = :awa, "luk" = :luk, "foc" = :foc, "fai" = :fai, '
             . 'xp = :xp, points_spent = :points_spent WHERE id = :id';
        $upd = $db->prepare($sql);
        $upd->execute(array_merge($attrs, [
            'name'    => ($name === '') ? null : $name,
            'outcome' => ($outcome === '') ? null : $outcome,
            'skin'    => $skin,
            'xp'      => $xp,
            'points_spent' => $pointsSpent,
            'id'      => $id,
        ]));

        $_SESSION['keeper_flash'] = 'Survivor #' . $id . ' updated.';
        header('Location: /keeper/survivors.php');
        exit;
    }

    // --- Delete a survivor (hard delete; removes stats + playtime history) ---
    // survivor_stats has no ON DELETE CASCADE, so a bare parent delete throws
    // once a stats row exists. Delete children explicitly in a transaction.
    if ($action === 'delete_survivor') {
        $db->exec('PRAGMA foreign_keys = ON');
        try {
            $db->beginTransaction();
            $delStats = $db->prepare('DELETE FROM survivor_stats WHERE survivor_id = ?');
            $delStats->execute([$id]);
            $delPlay = $db->prepare('DELETE FROM survivor_playtime WHERE survivor_id = ?');
            $delPlay->execute([$id]);
            $del = $db->prepare('DELETE FROM survivors WHERE id = ?');
            $del->execute([$id]);
            $db->commit();
            $_SESSION['keeper_flash'] = 'Survivor #' . $id . ' deleted.';
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['keeper_flash'] = 'Failed to delete survivor #' . $id . '.';
        }
        header('Location: /keeper/survivors.php');
        exit;
    }

    header('Location: /keeper/survivors.php');
    exit;
}

$pageTitle = 'Survivors — Keeper';
$pageCss = ['/css/keeper-survivors.css'];
$pageJs = ['/js/keeper-survivors.js'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$db = grave_db();

$totalSurvivors = (int) $db->query('SELECT COUNT(*) FROM survivors')->fetchColumn();
$activeSurvivors = (int) $db->query('SELECT COUNT(*) FROM survivors WHERE ended_at IS NULL')->fetchColumn();
$endedSurvivors = (int) $db->query('SELECT COUNT(*) FROM survivors WHERE ended_at IS NOT NULL')->fetchColumn();

// Every survivor, joined to its owner for the username. Reserved attribute
// columns are quoted. Newest first.
$survivors = $db->query(
    'SELECT s.id, s.user_id, s.ref, s.name, s.skin, s.started_at, s.ended_at, s.outcome,
            s."str" AS str, s.agi, s."end" AS end, s."int" AS int, s.awa, s.luk, s.foc, s.fai,
            s.xp, s.points_spent, u.username AS owner
     FROM survivors s
     LEFT JOIN users u ON u.id = s.user_id
     ORDER BY s.id DESC'
)->fetchAll();
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Survivors</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="keeper-stats">
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Total Survivors</p>
        <p class="keeper-stat-tile__value"><?= number_format($totalSurvivors) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Active</p>
        <p class="keeper-stat-tile__value"><?= number_format($activeSurvivors) ?></p>
      </div>
      <div class="card keeper-stat-tile">
        <p class="keeper-stat-tile__label text-muted">Ended</p>
        <p class="keeper-stat-tile__value"><?= number_format($endedSurvivors) ?></p>
      </div>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">All Survivors</h2>
      <div class="keeper-table-scroll">
        <table class="keeper-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Owner</th>
              <th>Name</th>
              <th>Skin</th>
              <th>XP</th>
              <th>Points</th>
              <th>Started</th>
              <th>Ended / Outcome</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($survivors as $s):
                $sid = (int) $s['id'];
                $owner = ($s['owner'] !== null && $s['owner'] !== '') ? (string) $s['owner'] : '—';
                $pointsEarned = survivor_points_earned((int) $s['xp']);
                $started = substr((string) $s['started_at'], 0, 10);
                $endedRaw = (string) ($s['ended_at'] ?? '');
                if ($endedRaw !== '') {
                    $endedShown = substr($endedRaw, 0, 10);
                    if ($s['outcome'] !== null && $s['outcome'] !== '') {
                        $endedShown .= ' · ' . $s['outcome'];
                    }
                } else {
                    $endedShown = 'Active';
                }

                // JSON payload for the edit modal (prefill via JS).
                $payload = [
                    'id'           => $sid,
                    'owner'        => $owner,
                    'ref'          => (string) $s['ref'],
                    'name'         => (string) ($s['name'] ?? ''),
                    'skin'         => (string) $s['skin'],
                    'outcome'      => (string) ($s['outcome'] ?? ''),
                    'started_at'   => (string) ($s['started_at'] ?? ''),
                    'xp'           => (int) $s['xp'],
                    'points_spent' => (int) $s['points_spent'],
                    'points_earned' => $pointsEarned,
                ];
                foreach (KEEPER_SURVIVOR_ATTRS as $a) {
                    $payload[$a] = (int) $s[$a];
                }
                $payloadAttr = htmlspecialchars(json_encode($payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <tr>
              <td class="keeper-cell-num"><?= $sid ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars($owner) ?>"><?= htmlspecialchars($owner) ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars((string) ($s['name'] ?? '')) ?>"><?= htmlspecialchars(($s['name'] !== null && $s['name'] !== '') ? (string) $s['name'] : '—') ?></td>
              <td class="keeper-cell-clamp keeper-cell-clamp--sm" title="<?= htmlspecialchars((string) $s['skin']) ?>"><?= htmlspecialchars(($s['skin'] !== null && $s['skin'] !== '') ? (string) $s['skin'] : '—') ?></td>
              <td class="keeper-cell-num"><?= number_format((int) $s['xp']) ?></td>
              <td class="keeper-cell-num"><?= (int) $s['points_spent'] ?> / <?= $pointsEarned ?></td>
              <td class="keeper-cell-nowrap"><?= htmlspecialchars($started) ?></td>
              <td class="keeper-cell-clamp" title="<?= htmlspecialchars($endedShown) ?>"><?= htmlspecialchars($endedShown) ?></td>
              <td>
                <div class="keeper-row-actions">
                  <button type="button" class="keeper-icon-btn" data-edit-survivor="<?= $payloadAttr ?>" title="Edit game data" aria-label="Edit game data">
                    <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/gamepad.svg" alt="">
                  </button>
                  <form method="post" action="/keeper/survivors.php" class="keeper-survivors-inline" onsubmit="return confirm('Delete survivor #<?= $sid ?> permanently? This removes its stats and playtime history.');">
                    <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                    <input type="hidden" name="action" value="delete_survivor">
                    <input type="hidden" name="id" value="<?= $sid ?>">
                    <button class="keeper-icon-btn keeper-icon-btn--danger" type="submit" title="Delete survivor" aria-label="Delete survivor">
                      <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash.svg" alt="">
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($survivors)): ?>
            <tr><td colspan="9" class="text-muted">No survivors found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Survivor game-data modal — prefilled via JS from data-edit-survivor. -->
  <div class="keeper-modal" id="survivor-modal" role="dialog" aria-modal="true" aria-labelledby="survivor-modal-title" hidden>
    <div class="keeper-modal__backdrop" data-close-survivor-modal></div>
    <div class="keeper-modal__panel keeper-modal__panel--wide">
      <div class="keeper-modal__head">
        <h2 class="keeper-modal__title" id="survivor-modal-title">Survivor Game Data</h2>
        <button type="button" class="keeper-modal__close" data-close-survivor-modal aria-label="Close">&times;</button>
      </div>

      <p class="text-muted keeper-users-hint">
        Owner <strong id="es-owner"></strong> ·
        ref <span id="es-ref"></span> ·
        skin <span id="es-skin-ctx"></span> ·
        started <span id="es-started"></span> ·
        points earned <strong id="es-earned"></strong>
      </p>

      <form method="post" action="/keeper/survivors.php" class="keeper-users-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <input type="hidden" name="action" value="edit_survivor">
        <input type="hidden" name="id" id="es-id" value="">

        <div class="keeper-users-grid">
          <label class="keeper-users-field">
            <span class="keeper-users-label">Name</span>
            <input class="field" type="text" name="name" id="es-name">
          </label>
          <label class="keeper-users-field">
            <span class="keeper-users-label">Skin</span>
            <input class="field" type="text" name="skin" id="es-skin">
          </label>
          <label class="keeper-users-field keeper-users-field--wide">
            <span class="keeper-users-label">Outcome</span>
            <input class="field" type="text" name="outcome" id="es-outcome">
          </label>
        </div>

        <fieldset class="keeper-users-statgroup">
          <legend>Attributes (0–<?= KEEPER_SURVIVOR_ATTR_CAP ?>)</legend>
          <div class="keeper-users-statgrid">
            <?php foreach (KEEPER_SURVIVOR_ATTRS as $a): ?>
            <label class="keeper-users-field">
              <span class="keeper-users-label"><?= htmlspecialchars(strtoupper($a)) ?></span>
              <input class="field" type="number" min="0" max="<?= KEEPER_SURVIVOR_ATTR_CAP ?>" name="<?= htmlspecialchars($a) ?>" id="es-<?= htmlspecialchars($a) ?>" value="0">
            </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <fieldset class="keeper-users-statgroup">
          <legend>Progression</legend>
          <div class="keeper-users-statgrid">
            <label class="keeper-users-field">
              <span class="keeper-users-label">XP (cumulative)</span>
              <input class="field" type="number" min="0" name="xp" id="es-xp" value="0">
            </label>
            <label class="keeper-users-field">
              <span class="keeper-users-label">Points Spent</span>
              <input class="field" type="number" min="0" name="points_spent" id="es-points_spent" value="0">
            </label>
          </div>
        </fieldset>

        <div class="keeper-users-modal-actions">
          <button type="button" class="btn btn-ghost" data-close-survivor-modal>Cancel</button>
          <button type="submit" class="btn btn-primary">Save Game Data</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

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
 * Keeper > Messages — author the lines generic NPCs speak in overhead talk
 * bubbles. The spawnable-NPC roster is DYNAMIC: "Update roster" fetches the
 * game's roster endpoint (env GAME_ROSTER_URL) and syncs npc_roster (adds new
 * NPCs, deactivates ones no longer reported — saved lines are kept). Messages
 * are edited per NPC and pulled by the game (which picks random ones).
 * All writes go to the HOST db via grave_db().
 */

/** Ensure the NPC tables exist even before the migration has run (idempotent). */
function keeper_ensure_npc_tables(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS npc_roster (
            name TEXT PRIMARY KEY, gender TEXT, role TEXT, height REAL,
            active INTEGER NOT NULL DEFAULT 1, seen_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS npc_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, npc_name TEXT NOT NULL, body TEXT NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1, weight INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (npc_name) REFERENCES npc_roster(name) ON DELETE CASCADE
        )'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_npc_messages_npc ON npc_messages(npc_name)');
}

/**
 * Fetch the roster from the game endpoint. Returns a list of
 * ['name'=>, 'gender'=>, 'role'=>, 'height'=>] or throws RuntimeException with
 * a human-readable reason. Accepts either {"characters":[...]} or a bare array.
 */
function keeper_fetch_game_roster(string $url, string $bearer): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => $bearer !== '' ? "Authorization: Bearer {$bearer}\r\n" : '',
            'timeout'       => 8,
            'ignore_errors' => true,
        ],
        'https' => [
            'method'        => 'GET',
            'header'        => $bearer !== '' ? "Authorization: Bearer {$bearer}\r\n" : '',
            'timeout'       => 8,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Could not reach the game roster endpoint.');
    }

    // $http_response_header is populated by the HTTP wrapper.
    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($status !== 0 && ($status < 200 || $status >= 300)) {
        throw new RuntimeException("Game endpoint returned HTTP {$status}.");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Game endpoint did not return valid JSON.');
    }

    // Accept {"characters":[...]} or a bare array of character objects.
    $list = array_key_exists('characters', $data) && is_array($data['characters'])
        ? $data['characters']
        : $data;

    $out = [];
    foreach ($list as $c) {
        if (!is_array($c)) {
            continue;
        }
        $name = trim((string) ($c['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $out[$name] = [
            'name'   => $name,
            'gender' => isset($c['gender']) ? (string) $c['gender'] : null,
            'role'   => isset($c['role']) ? (string) $c['role'] : null,
            'height' => isset($c['height']) && is_numeric($c['height']) ? (float) $c['height'] : null,
        ];
    }

    if (!$out) {
        throw new RuntimeException('Game endpoint returned no named characters.');
    }

    return array_values($out);
}

/**
 * Sync fetched roster into npc_roster: upsert each, mark active=1; any NPC not
 * in this fetch becomes active=0 (kept, its lines survive). Returns a summary.
 */
function keeper_sync_roster(PDO $db, array $roster): array
{
    $names = array_map(static fn(array $c): string => $c['name'], $roster);

    $db->beginTransaction();
    try {
        $upsert = $db->prepare(
            'INSERT INTO npc_roster (name, gender, role, height, active, seen_at)
             VALUES (:name, :gender, :role, :height, 1, CURRENT_TIMESTAMP)
             ON CONFLICT(name) DO UPDATE SET
                gender = excluded.gender, role = excluded.role, height = excluded.height,
                active = 1, seen_at = CURRENT_TIMESTAMP'
        );
        foreach ($roster as $c) {
            $upsert->execute([
                ':name'   => $c['name'],
                ':gender' => $c['gender'],
                ':role'   => $c['role'],
                ':height' => $c['height'],
            ]);
        }

        // Deactivate NPCs no longer reported (keep the rows + their messages).
        if ($names) {
            $ph = implode(',', array_fill(0, count($names), '?'));
            $stmt = $db->prepare("UPDATE npc_roster SET active = 0 WHERE name NOT IN ($ph)");
            $stmt->execute($names);
        } else {
            $db->exec('UPDATE npc_roster SET active = 0');
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    return ['count' => count($roster)];
}

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

$db = grave_db();
keeper_ensure_npc_tables($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/messages.php');
        exit;
    }

    // --- Update roster: fetch the game endpoint and sync npc_roster ---
    if (isset($_POST['update_roster'])) {
        $url    = trim((string) env('GAME_ROSTER_URL', ''));
        $bearer = trim((string) env('GAME_API_KEY', ''));

        if ($url === '') {
            $_SESSION['keeper_flash'] = 'GAME_ROSTER_URL is not set in .env, so the roster can\'t be fetched yet.';
            header('Location: /keeper/messages.php');
            exit;
        }

        try {
            $roster  = keeper_fetch_game_roster($url, $bearer);
            $summary = keeper_sync_roster($db, $roster);
            $_SESSION['keeper_flash'] = "Roster updated — {$summary['count']} character(s) synced from the game.";
        } catch (Throwable $e) {
            $_SESSION['keeper_flash'] = 'Roster update failed: ' . $e->getMessage();
        }

        header('Location: /keeper/messages.php');
        exit;
    }

    // --- Save messages for a single NPC ---
    if (isset($_POST['save_messages'])) {
        $npc = trim((string) ($_POST['npc_name'] ?? ''));

        // Confirm the NPC exists in the roster before writing its lines.
        $exists = $db->prepare('SELECT 1 FROM npc_roster WHERE name = ?');
        $exists->execute([$npc]);
        if ($npc === '' || $exists->fetchColumn() === false) {
            $_SESSION['keeper_flash'] = 'Unknown NPC. Nothing was saved.';
            header('Location: /keeper/messages.php');
            exit;
        }

        $rows = $_POST['rows'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $db->beginTransaction();
        try {
            $update = $db->prepare(
                'UPDATE npc_messages SET body = ?, enabled = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND npc_name = ?'
            );
            $delete = $db->prepare('DELETE FROM npc_messages WHERE id = ? AND npc_name = ?');
            $insert = $db->prepare(
                'INSERT INTO npc_messages (npc_name, body, enabled) VALUES (?, ?, ?)'
            );

            foreach ($rows as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $body    = trim((string) ($row['body'] ?? ''));
                $enabled = empty($row['enabled']) ? 0 : 1;

                if ($key === 'new') {
                    if ($body !== '') {
                        $insert->execute([$npc, $body, $enabled]);
                    }
                    continue;
                }

                $id = (int) $key;
                if ($id <= 0) {
                    continue;
                }
                if (!empty($row['delete']) || $body === '') {
                    // Explicit delete, or an existing line emptied out = remove it.
                    $delete->execute([$id, $npc]);
                    continue;
                }
                $update->execute([$body, $enabled, $id, $npc]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['keeper_flash'] = 'Save failed: ' . $e->getMessage();
            header('Location: /keeper/messages.php');
            exit;
        }

        $_SESSION['keeper_flash'] = "Saved lines for {$npc}.";
        header('Location: /keeper/messages.php');
        exit;
    }

    // Unknown POST — just bounce back.
    header('Location: /keeper/messages.php');
    exit;
}

$pageTitle = 'Messages — Keeper';
$pageCss = ['/css/keeper-messages.css'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

// Active NPCs (in the latest roster) + their message counts. Inactive NPCs
// (removed from the game) are hidden but their rows/lines are retained.
$npcs = $db->query(
    'SELECT r.name, r.gender, r.role,
            (SELECT COUNT(*) FROM npc_messages m WHERE m.npc_name = r.name) AS msg_count
     FROM npc_roster r
     WHERE r.active = 1
     ORDER BY r.role, r.name'
)->fetchAll();

// Preload all messages grouped by NPC (one query, avoids N+1).
$messagesByNpc = [];
foreach ($db->query('SELECT id, npc_name, body, enabled FROM npc_messages ORDER BY id')->fetchAll() as $m) {
    $messagesByNpc[$m['npc_name']][] = $m;
}

$rosterConfigured = trim((string) env('GAME_ROSTER_URL', '')) !== '';
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Messages</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">NPC Roster</h2>
      <p class="text-muted keeper-messages-hint">
        The spawnable NPCs from the game. Press <strong>Update roster</strong> to fetch the current list from the game
        <?= $rosterConfigured ? '' : '(set <code>GAME_ROSTER_URL</code> in <code>.env</code> first)' ?>.
        Characters removed from the game are hidden here, but their saved lines are kept.
      </p>

      <form method="post" action="/keeper/messages.php" class="keeper-messages-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <div class="keeper-messages-actions">
          <button type="submit" name="update_roster" value="1" class="btn btn-primary"<?= $rosterConfigured ? '' : ' disabled title="Set GAME_ROSTER_URL in .env"' ?>>Update roster</button>
        </div>
      </form>
    </div>

    <?php if (empty($npcs)): ?>
    <div class="card keeper-table-card">
      <p class="text-muted">No NPCs registered yet. Press <strong>Update roster</strong> above to pull the character list from the game, then add lines for each.</p>
    </div>
    <?php else: ?>
      <?php foreach ($npcs as $npc): ?>
      <?php $lines = $messagesByNpc[$npc['name']] ?? []; ?>
      <div class="card keeper-table-card">
        <h2 class="keeper-table-card__heading">
          <?= htmlspecialchars($npc['name']) ?>
          <?php if (!empty($npc['role'])): ?><span class="keeper-messages-role"><?= htmlspecialchars($npc['role']) ?></span><?php endif; ?>
        </h2>
        <p class="text-muted keeper-messages-hint">Lines <?= htmlspecialchars($npc['name']) ?> might say. Uncheck <em>On</em> to keep a line but stop it being spoken. Clear a line's text to remove it.</p>

        <form method="post" action="/keeper/messages.php" class="keeper-messages-form">
          <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
          <input type="hidden" name="npc_name" value="<?= htmlspecialchars($npc['name']) ?>">

          <div class="keeper-table-scroll">
            <table class="keeper-table keeper-messages-table">
              <thead>
                <tr>
                  <th>Line</th>
                  <th class="keeper-messages-col-on">On</th>
                  <th class="keeper-messages-col-del">Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lines as $line): ?>
                <tr>
                  <td><input class="field" type="text" name="rows[<?= (int) $line['id'] ?>][body]" value="<?= htmlspecialchars((string) $line['body']) ?>" placeholder="What they say…"></td>
                  <td class="keeper-messages-col-on"><input type="checkbox" name="rows[<?= (int) $line['id'] ?>][enabled]" value="1" <?= (int) $line['enabled'] === 1 ? 'checked' : '' ?> aria-label="Enabled"></td>
                  <td class="keeper-messages-col-del"><input type="checkbox" name="rows[<?= (int) $line['id'] ?>][delete]" value="1" aria-label="Delete line"></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                <tr><td colspan="3" class="text-muted">No lines yet. Add one below.</td></tr>
                <?php endif; ?>
                <tr class="keeper-messages-new">
                  <td><input class="field" type="text" name="rows[new][body]" value="" placeholder="Add a new line…"></td>
                  <td class="keeper-messages-col-on"><input type="checkbox" name="rows[new][enabled]" value="1" checked aria-label="Enabled"></td>
                  <td class="keeper-messages-col-del"><span class="text-muted">&mdash;</span></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="keeper-messages-actions">
            <button type="submit" name="save_messages" value="1" class="btn btn-primary">Save <?= htmlspecialchars($npc['name']) ?></button>
          </div>
        </form>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

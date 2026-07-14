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
 * Open a direct PDO connection to the forum's SQLite database — Keeper never
 * includes bbs/ code, it connects directly.
 */
if (!function_exists('keeper_forum_db')) {
    function keeper_forum_db(): PDO
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $dsn = 'sqlite:' . __DIR__ . '/../bbs/forum.db';

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }
}

/** Normalize a section key to a URL-safe slug (lowercase a-z0-9 and dashes). */
function keeper_slugify_key(string $key): string
{
    $key = strtolower(trim($key));
    $key = preg_replace('/[^a-z0-9]+/', '-', $key);

    return trim((string) $key, '-');
}

/** True when the value is a real calendar date in YYYY-MM-DD form. */
function keeper_valid_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

/** Read one value from the HOST settings table (null when unset). */
function keeper_load_host_setting(PDO $db, string $key): ?string
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return ($value === false) ? null : (string) $value;
}

/** Upsert a HOST setting; an empty value clears (deletes) the key. */
function keeper_save_host_setting(PDO $db, string $key, string $value): void
{
    if ($value === '') {
        $stmt = $db->prepare('DELETE FROM settings WHERE key = ?');
        $stmt->execute([$key]);

        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([$key, $value]);
}

/** Read the feed_sections list from the forum settings table. */
function keeper_load_feed_sections(PDO $db): array
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['feed_sections']);
    $raw = $stmt->fetchColumn();

    $sections = ($raw !== false) ? json_decode((string) $raw, true) : null;

    return is_array($sections) ? array_values(array_filter($sections, 'is_array')) : [];
}

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

$db = keeper_forum_db();

// The forum installer owns this schema; ensure it exists even if no forum
// page has run yet (idempotent, matches bbs/install.php).
$db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');

$categories = $db->query('SELECT id, name FROM categories ORDER BY sort_order, id')->fetchAll();
$categoryIds = array_map(static fn(array $c): int => (int) $c['id'], $categories);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/settings.php');
        exit;
    }

    // --- Season form (season dates live in the HOST settings table) ---
    if (isset($_POST['save_season'])) {
        $start = trim((string) ($_POST['season_start'] ?? ''));
        $end   = trim((string) ($_POST['season_end'] ?? ''));

        if (($start !== '' && !keeper_valid_date($start)) || ($end !== '' && !keeper_valid_date($end))) {
            $_SESSION['keeper_flash'] = 'Season dates must be valid dates (YYYY-MM-DD). Nothing was saved.';
            header('Location: /keeper/settings.php');
            exit;
        }

        if ($start !== '' && $end !== '' && $end < $start) {
            $_SESSION['keeper_flash'] = 'Season End must be on or after Season Start. Nothing was saved.';
            header('Location: /keeper/settings.php');
            exit;
        }

        $hostDb = grave_db();
        keeper_save_host_setting($hostDb, 'season_start', $start);
        keeper_save_host_setting($hostDb, 'season_end', $end);

        $_SESSION['keeper_flash'] = 'Season dates saved.';
        header('Location: /keeper/settings.php');
        exit;
    }

    // --- Feed-sections form (forum settings table) ---
    $rows = $_POST['rows'] ?? [];
    if (!is_array($rows)) {
        $rows = [];
    }

    // The blank "add section" row travels as rows[new][...]; it is only kept
    // when the admin actually filled it in (key or label present).
    $sections = [];
    $errors = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!empty($row['delete'])) {
            continue;
        }

        $key   = keeper_slugify_key((string) ($row['key'] ?? ''));
        $label = trim((string) ($row['label'] ?? ''));
        $catId = (int) ($row['category_id'] ?? 0);
        $limit = (int) ($row['limit'] ?? 0);

        if ($key === '' && $label === '') {
            continue; // untouched blank row
        }

        if ($key === '') {
            $errors[] = 'Every section needs a key.';
            continue;
        }
        if ($label === '') {
            $label = ucwords(str_replace('-', ' ', $key));
        }
        if (!in_array($catId, $categoryIds, true)) {
            $errors[] = "Section \"{$key}\" needs a valid forum.";
            continue;
        }
        if (isset($sections[$key])) {
            $errors[] = "Duplicate section key \"{$key}\".";
            continue;
        }

        $sections[$key] = [
            'key'         => $key,
            'label'       => $label,
            'category_id' => $catId,
            'limit'       => max(1, min(20, $limit ?: 3)),
        ];
    }

    if ($errors) {
        $_SESSION['keeper_flash'] = implode(' ', array_unique($errors)) . ' Nothing was saved.';
        header('Location: /keeper/settings.php');
        exit;
    }

    $upsert = $db->prepare(
        'INSERT INTO settings (key, value) VALUES (?, ?)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value'
    );
    $upsert->execute(['feed_sections', json_encode(array_values($sections))]);

    $_SESSION['keeper_flash'] = 'Feed sections saved.';
    header('Location: /keeper/settings.php');
    exit;
}

$pageTitle = 'Settings — Keeper';
$pageCss = ['/css/keeper-settings.css'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$feedSections = keeper_load_feed_sections($db);

$hostDb = grave_db();
$seasonStart = (string) (keeper_load_host_setting($hostDb, 'season_start') ?? '');
$seasonEnd   = (string) (keeper_load_host_setting($hostDb, 'season_end') ?? '');
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Settings</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Season</h2>
      <p class="text-muted keeper-settings-hint">The current season window. Leave a date blank to clear it.</p>

      <form method="post" action="/keeper/settings.php" class="keeper-settings-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">

        <div class="keeper-settings-season">
          <label class="keeper-settings-season__field">
            <span class="keeper-settings-season__label">Season Start</span>
            <input class="field" type="date" name="season_start" value="<?= htmlspecialchars($seasonStart) ?>" aria-label="Season Start">
          </label>
          <label class="keeper-settings-season__field">
            <span class="keeper-settings-season__label">Season End</span>
            <input class="field" type="date" name="season_end" value="<?= htmlspecialchars($seasonEnd) ?>" aria-label="Season End">
          </label>
        </div>

        <div class="keeper-settings-actions">
          <button type="submit" name="save_season" value="1" class="btn btn-primary">Save Season</button>
        </div>
      </form>
    </div>

    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Site Feed Sections</h2>
      <p class="text-muted keeper-settings-hint">Map a site section to the forum that feeds it via <code>/api/feed?section=&lt;key&gt;</code>. The home page reads the <strong>latest-news</strong> section.</p>

      <form method="post" action="/keeper/settings.php" class="keeper-settings-form">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">

        <div class="keeper-table-scroll">
          <table class="keeper-table keeper-settings-table">
            <thead>
              <tr>
                <th>Label</th>
                <th>Key</th>
                <th>Forum</th>
                <th>Limit</th>
                <th>Delete</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($feedSections as $i => $s): ?>
              <tr>
                <td><input class="field" type="text" name="rows[<?= $i ?>][label]" value="<?= htmlspecialchars((string) ($s['label'] ?? '')) ?>" placeholder="Section label"></td>
                <td><input class="field" type="text" name="rows[<?= $i ?>][key]" value="<?= htmlspecialchars((string) ($s['key'] ?? '')) ?>" placeholder="section-key"></td>
                <td>
                  <select class="field" name="rows[<?= $i ?>][category_id]">
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) ($s['category_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input class="field keeper-settings-limit" type="number" name="rows[<?= $i ?>][limit]" value="<?= (int) ($s['limit'] ?? 3) ?>" min="1" max="20" placeholder="3"></td>
                <td class="keeper-settings-delete"><input type="checkbox" name="rows[<?= $i ?>][delete]" value="1" aria-label="Delete section"></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($feedSections)): ?>
              <tr><td colspan="5" class="text-muted">No feed sections configured yet. Add one below.</td></tr>
              <?php endif; ?>
              <tr class="keeper-settings-new">
                <td><input class="field" type="text" name="rows[new][label]" value="" placeholder="New section label"></td>
                <td><input class="field" type="text" name="rows[new][key]" value="" placeholder="new-section-key"></td>
                <td>
                  <select class="field" name="rows[new][category_id]">
                    <option value="0">— Forum —</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td><input class="field keeper-settings-limit" type="number" name="rows[new][limit]" value="" min="1" max="20" placeholder="3"></td>
                <td class="keeper-settings-delete"><span class="text-muted">&mdash;</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="keeper-settings-actions">
          <button type="submit" name="save_feed" value="1" class="btn btn-primary">Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

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
 * Keeper > Media — view the site's trailer(s) and music, and flag which are
 * "active". The FILE LIST is scanned live from assets/video/ + assets/music/
 * (drop a file in, it appears — no ingest). Only the active flags persist, in
 * the host `settings` table under the `media_active` key (a JSON list of
 * active relative paths). What "active" drives on the front-end is wired later.
 */

const KEEPER_MEDIA_DIRS = [
    'video' => ['dir' => 'assets/video', 'exts' => ['mp4', 'webm', 'mov']],
    'music' => ['dir' => 'assets/music', 'exts' => ['mp3', 'ogg', 'wav', 'm4a']],
];

/** Read the active-media list (relative paths) from host settings. */
function keeper_media_active(PDO $db): array
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['media_active']);
    $raw = $stmt->fetchColumn();
    $list = ($raw !== false) ? json_decode((string) $raw, true) : null;

    return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
}

/** Persist the active-media list. */
function keeper_media_save_active(PDO $db, array $paths): void
{
    $paths = array_values(array_unique(array_filter($paths, 'is_string')));
    $stmt = $db->prepare(
        'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute(['media_active', json_encode($paths)]);
}

/** Read the filename→title override map from host settings. */
function keeper_media_titles(PDO $db): array
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['media_titles']);
    $raw = $stmt->fetchColumn();
    $map = ($raw !== false) ? json_decode((string) $raw, true) : null;

    return is_array($map) ? $map : [];
}

/** Persist the filename→title override map (blank titles are dropped). */
function keeper_media_save_titles(PDO $db, array $map): void
{
    $clean = [];
    foreach ($map as $file => $title) {
        $title = trim((string) $title);
        if ($title !== '') {
            $clean[(string) $file] = $title;
        }
    }
    $stmt = $db->prepare(
        'INSERT INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)
         ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute(['media_titles', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

/**
 * Display title for a media file: the admin override if set, else the
 * prettified filename (underscores → spaces). SHARED by the front-end
 * (media.php) so the two always agree — keep the logic identical.
 */
function keeper_media_title(string $filename, array $titleMap): string
{
    if (isset($titleMap[$filename]) && trim((string) $titleMap[$filename]) !== '') {
        return trim((string) $titleMap[$filename]);
    }
    $base = pathinfo($filename, PATHINFO_FILENAME);
    return trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $base)));
}

/** Scan a media folder and return [ ['path'=>webpath, 'name'=>file, 'size'=>bytes], ... ]. */
function keeper_media_scan(string $dir, array $exts): array
{
    $abs = __DIR__ . '/../' . $dir;
    if (!is_dir($abs)) {
        return [];
    }
    $out = [];
    foreach (scandir($abs) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (!in_array($ext, $exts, true)) {
            continue;
        }
        $out[] = [
            'path' => '/' . $dir . '/' . $f,   // web path, e.g. /assets/music/Dust.mp3
            'name' => $f,
            'size' => (int) @filesize($abs . '/' . $f),
        ];
    }
    // Sort by filename for a stable list.
    usort($out, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

    return $out;
}

/** Human-readable file size. */
function keeper_media_size(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
}

// Keeper-scoped CSRF token.
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

$db = grave_db();
$db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/media.php');
        exit;
    }

    // Save the editable song titles (filename → title overrides).
    if (isset($_POST['save_titles'])) {
        $posted = $_POST['titles'] ?? [];
        if (!is_array($posted)) {
            $posted = [];
        }

        // Only accept filenames that actually exist among the scanned media.
        $knownNames = [];
        foreach (KEEPER_MEDIA_DIRS as $cfg) {
            foreach (keeper_media_scan($cfg['dir'], $cfg['exts']) as $m) {
                $knownNames[$m['name']] = true;
            }
        }

        $map = [];
        foreach ($posted as $file => $title) {
            $file = (string) $file;
            if (isset($knownNames[$file])) {
                $map[$file] = (string) $title;
            }
        }

        keeper_media_save_titles($db, $map);
        $_SESSION['keeper_flash'] = 'Titles saved.';
        header('Location: /keeper/media.php');
        exit;
    }

    // Toggle one item's active state. Only accept paths that actually exist
    // among the scanned media (never trust an arbitrary posted path).
    if (isset($_POST['toggle_active'])) {
        $path = (string) ($_POST['path'] ?? '');

        $known = [];
        foreach (KEEPER_MEDIA_DIRS as $cfg) {
            foreach (keeper_media_scan($cfg['dir'], $cfg['exts']) as $m) {
                $known[$m['path']] = true;
            }
        }

        if (isset($known[$path])) {
            $active = keeper_media_active($db);
            if (in_array($path, $active, true)) {
                $active = array_values(array_diff($active, [$path]));
                $_SESSION['keeper_flash'] = 'Deactivated ' . basename($path) . '.';
            } else {
                $active[] = $path;
                $_SESSION['keeper_flash'] = 'Activated ' . basename($path) . '.';
            }
            keeper_media_save_active($db, $active);
        } else {
            $_SESSION['keeper_flash'] = 'Unknown media file. Nothing changed.';
        }

        header('Location: /keeper/media.php');
        exit;
    }

    header('Location: /keeper/media.php');
    exit;
}

$pageTitle = 'Media — Keeper';
$pageCss = ['/css/keeper-media.css'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$activeList = keeper_media_active($db);
$activeSet  = array_fill_keys($activeList, true);
$titleMap   = keeper_media_titles($db);

$videos = keeper_media_scan(KEEPER_MEDIA_DIRS['video']['dir'], KEEPER_MEDIA_DIRS['video']['exts']);
$tracks = keeper_media_scan(KEEPER_MEDIA_DIRS['music']['dir'], KEEPER_MEDIA_DIRS['music']['exts']);
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Media</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <!-- ===== Video / Trailers ===== -->
    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Trailers <span class="keeper-media-count"><?= count($videos) ?></span></h2>
      <?php if (empty($videos)): ?>
        <p class="text-muted">No video files in <code>assets/video/</code>.</p>
      <?php else: ?>
        <div class="keeper-media-grid keeper-media-grid--video">
          <?php foreach ($videos as $m): $isActive = isset($activeSet[$m['path']]); ?>
          <div class="keeper-media-item<?= $isActive ? ' is-active' : '' ?>">
            <video class="keeper-media-video" controls preload="metadata" src="<?= htmlspecialchars($m['path']) ?>"></video>
            <div class="keeper-media-meta">
              <span class="keeper-media-name"><?= htmlspecialchars($m['name']) ?></span>
              <span class="keeper-media-size"><?= htmlspecialchars(keeper_media_size($m['size'])) ?></span>
            </div>
            <form method="post" action="/keeper/media.php" class="keeper-media-toggle-form">
              <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
              <input type="hidden" name="path" value="<?= htmlspecialchars($m['path']) ?>">
              <button type="submit" name="toggle_active" value="1" class="keeper-media-toggle<?= $isActive ? ' is-on' : '' ?>">
                <?= $isActive ? 'Active' : 'Inactive' ?>
              </button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- ===== Music ===== -->
    <div class="card keeper-table-card">
      <h2 class="keeper-table-card__heading">Music <span class="keeper-media-count"><?= count($tracks) ?></span></h2>
      <?php if (empty($tracks)): ?>
        <p class="text-muted">No audio files in <code>assets/music/</code>.</p>
      <?php else: ?>
        <p class="text-muted keeper-media-hint">Edit a track's <strong>Title</strong> to change what shows on the public <code>/media</code> page. Leave blank to use the filename.</p>

        <!-- Per-row toggle forms live OUTSIDE the table (buttons target them via
             the form= attribute) so the titles form can wrap the table without
             nesting forms. -->
        <?php foreach ($tracks as $i => $m): ?>
        <form method="post" action="/keeper/media.php" id="mt-<?= $i ?>" class="keeper-media-toggle-form">
          <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
          <input type="hidden" name="path" value="<?= htmlspecialchars($m['path']) ?>">
        </form>
        <?php endforeach; ?>

        <form method="post" action="/keeper/media.php" class="keeper-media-titles-form">
          <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
          <div class="keeper-table-scroll">
            <table class="keeper-table keeper-media-table">
              <thead>
                <tr>
                  <th>File</th>
                  <th>Title</th>
                  <th>Preview</th>
                  <th>Size</th>
                  <th>Active</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tracks as $i => $m): $isActive = isset($activeSet[$m['path']]); ?>
                <tr class="<?= $isActive ? 'is-active' : '' ?>">
                  <td class="keeper-media-name"><?= htmlspecialchars($m['name']) ?></td>
                  <td>
                    <input class="field keeper-media-title-input" type="text"
                           name="titles[<?= htmlspecialchars($m['name']) ?>]"
                           value="<?= htmlspecialchars((string) ($titleMap[$m['name']] ?? '')) ?>"
                           placeholder="<?= htmlspecialchars(keeper_media_title($m['name'], [])) ?>">
                  </td>
                  <td class="keeper-media-audio-cell">
                    <audio class="keeper-media-audio" controls preload="none" src="<?= htmlspecialchars($m['path']) ?>"></audio>
                  </td>
                  <td class="keeper-media-size"><?= htmlspecialchars(keeper_media_size($m['size'])) ?></td>
                  <td>
                    <button type="submit" name="toggle_active" value="1" form="mt-<?= $i ?>" class="keeper-media-toggle<?= $isActive ? ' is-on' : '' ?>">
                      <?= $isActive ? 'Active' : 'Inactive' ?>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div class="keeper-media-titles-actions">
            <button type="submit" name="save_titles" value="1" class="btn btn-primary">Save Titles</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

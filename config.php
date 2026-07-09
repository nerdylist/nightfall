<?php
/**
 * THE DEAD LAST — config loader
 *
 * Parses web/.env, exposes values via env(), and wires in the real
 * SQLite connection helper (web/lib/db.php). Use grave_db() to get a
 * PDO connection — see lib/db.php for details.
 */

function grave_load_env(string $path): array
{
    $values = [];

    if (!is_file($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $values[trim($key)] = trim($value);
    }

    return $values;
}

// SSO: host (root) and forum (/bbs) share ONE PHP session cookie (default
// name PHPSESSID, path '/'). Whichever app calls session_start() first wins.
// Guarded so pages that already start their own session (login.php,
// logout.php, keeper/*) are not double-started.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$GLOBALS['__grave_env'] = grave_load_env(__DIR__ . '/.env');

function env(string $key, $default = null)
{
    return $GLOBALS['__grave_env'][$key] ?? $default;
}

/**
 * Resolve a root-relative asset path (e.g. '/css/base.css') against the web
 * root and append a '?v=' cache-busting query string based on the file's
 * mtime. Falls back to the bare path if the file can't be found.
 */
function asset_url(string $path): string
{
    $file = __DIR__ . '/' . ltrim($path, '/');
    $mtime = is_file($file) ? filemtime($file) : false;

    return $mtime !== false ? $path . '?v=' . $mtime : $path;
}

require_once __DIR__ . '/lib/db.php';

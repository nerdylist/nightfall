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

// ~1 year in seconds (365 * 24 * 60 * 60).
const GRAVE_SESSION_LIFETIME = 31536000;

// SSO: host (root) and forum (/bbs) share ONE PHP session cookie (default
// name PHPSESSID, path '/'). Whichever app calls session_start() first wins.
// Guarded so pages that already start their own session (login.php,
// logout.php, keeper/*) are not double-started. The cookie is persistent
// (~1 year) so login survives browser restarts until explicit logout or the
// cookie is cleared. Params match the forum (bbs/lib/auth.php: path '/',
// httponly, samesite Lax, secure) so both apps stay cookie-compatible — the
// only addition is the long lifetime.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => GRAVE_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => true,
    ]);
    session_start();

    // Sliding expiry: re-issue the session cookie with a fresh ~1-year window
    // on each request so an active user is never logged out mid-year. Skipped
    // on CLI / after headers are sent to avoid warnings. Re-issued with the
    // array-options form (PHP 7.3+) so the refreshed cookie keeps SameSite=Lax
    // / Secure / HttpOnly / path='/' and only the expiry advances.
    if (PHP_SAPI !== 'cli' && !headers_sent() && isset($_COOKIE[session_name()])) {
        setcookie(session_name(), $_COOKIE[session_name()], [
            'expires'  => time() + GRAVE_SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
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
require_once __DIR__ . '/lib/auth.php';

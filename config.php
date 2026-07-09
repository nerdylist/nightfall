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

$GLOBALS['__grave_env'] = grave_load_env(__DIR__ . '/.env');

function env(string $key, $default = null)
{
    return $GLOBALS['__grave_env'][$key] ?? $default;
}

require_once __DIR__ . '/lib/db.php';

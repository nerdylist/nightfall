<?php
/**
 * GRAVE RISING — config loader (stub)
 *
 * Prototype-only. This shows the shape the wiring pass will fill in:
 * parse web/.env, expose values via env(), and open a real SQLite
 * connection. For now it just stubs env() with safe placeholder reads
 * so pages can reference config without a live backend.
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

// BACKEND WIRING GOES HERE — open a real SQLite connection using
// env('DB_PATH'), e.g.:
//   $pdo = new PDO('sqlite:' . env('DB_PATH'));
//   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Not implemented in this prototype — no .db file is created.

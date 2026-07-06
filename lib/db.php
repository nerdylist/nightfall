<?php
/**
 * GRAVE RISING — SQLite PDO connection helper.
 *
 * Returns a shared PDO connection to the SQLite database at env('DB_PATH').
 * Creates the containing directory if missing. Does NOT run migrations —
 * see web/bin/setup-db.php for schema setup.
 */

function grave_db_path(): string
{
    $path = env('DB_PATH', './data/graverising.sqlite');

    // Resolve relative paths against the web/ root (one level up from lib/).
    if (!str_starts_with($path, '/')) {
        $webRoot = dirname(__DIR__);
        $path = $webRoot . '/' . preg_replace('#^\./#', '', $path);
    }

    return $path;
}

function grave_db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $path = grave_db_path();
    $dir = dirname($path);

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}

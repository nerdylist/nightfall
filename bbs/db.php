<?php
/**
 * SQLite database access (PDO) for the Nexus forum.
 *
 * Exposes forum_db(): PDO as a singleton. On first connect, if the schema
 * is missing (no `users` table), the installer is invoked automatically so
 * the database is created and seeded transparently. This file echoes nothing.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('forum_db')) {
    /**
     * Return the shared PDO connection, creating and installing it on demand.
     *
     * @return PDO
     */
    function forum_db()
    {
        static $pdo = null;

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        global $CONFIG;

        $dsn = 'sqlite:' . __DIR__ . '/' . $CONFIG['DB_FILE'];

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

        // Always run the installer on first connect. forum_install() is fully
        // idempotent (CREATE IF NOT EXISTS, guarded migrations, guarded seeds)
        // and produces no output, so running it every request keeps the schema
        // — including the categories.color migration — up to date on existing
        // databases. The static $pdo singleton means it runs at most once per
        // request.
        require_once __DIR__ . '/install.php';
        forum_install($pdo);

        return $pdo;
    }
}

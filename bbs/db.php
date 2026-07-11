<?php
/**
 * SQLite database access (PDO) for the Nexus forum.
 *
 * Exposes forum_db(): PDO as a singleton. On first connect the HOST database
 * is attached as `host` (its users table is the single shared userbase); if
 * the host schema is missing (fresh checkout), the host migration set is
 * applied first, then the idempotent forum installer runs — so a brand-new
 * environment self-installs end to end on the first /bbs page load. This
 * file echoes nothing.
 */

require_once __DIR__ . '/config.php';

if (!function_exists('forum_host_db_path')) {
    /**
     * Absolute path to the HOST (THE DEAD LAST) SQLite database, resolved
     * from the host .env DB_PATH without pulling in host config.php (which
     * redefines env()/db helpers). The host users table IS the forum's
     * userbase; forum_db() attaches this file as `host` so queries address
     * it as host.users.
     *
     * @return string
     */
    function forum_host_db_path()
    {
        $hostRoot = dirname(__DIR__); // bbs -> host root
        $dbPath = 'data/graverising.sqlite';
        $envFile = $hostRoot . '/.env';
        if (is_file($envFile) && is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') { continue; }
                $pos = strpos($line, '=');
                if ($pos === false) { continue; }
                if (trim(substr($line, 0, $pos)) === 'DB_PATH') {
                    $dbPath = trim(substr($line, $pos + 1));
                    break;
                }
            }
        }
        // Resolve relative-to-host-root (mirrors host lib/db.php).
        if (strpos($dbPath, '/') !== 0) {
            $dbPath = $hostRoot . '/' . preg_replace('#^\./#', '', $dbPath);
        }
        return $dbPath;
    }
}

if (!function_exists('forum_host_db_bootstrap')) {
    /**
     * Fresh-checkout support: make sure the attached host database actually
     * has its schema. Costs ONE sqlite_master lookup on the already-open
     * connection per connect (the hot path); only when host.users is missing
     * does it apply the host migration set (migrations/*.sql, tracked in
     * schema_migrations — the same scheme bin/setup-db.php uses) through a
     * short-lived direct connection. Idempotent, echoes nothing.
     *
     * @param PDO    $pdo      Forum connection with the host db attached.
     * @param string $hostPath Host database file path (already attached).
     * @return void
     */
    function forum_host_db_bootstrap(PDO $pdo, $hostPath)
    {
        $has = $pdo->query(
            "SELECT 1 FROM host.sqlite_master WHERE type = 'table' AND name = 'users'"
        )->fetch();
        if ($has !== false) {
            return;
        }

        $host = new PDO('sqlite:' . $hostPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $host->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                migration  TEXT UNIQUE NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $applied = [];
        foreach ($host->query('SELECT migration FROM schema_migrations') as $row) {
            $applied[$row['migration']] = true;
        }
        $files = glob(dirname(__DIR__) . '/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        foreach ($files as $file) {
            $name = basename($file);
            if (isset($applied[$name])) {
                continue;
            }
            $sql = file_get_contents($file);
            if ($sql === false) {
                continue;
            }
            $host->beginTransaction();
            try {
                $host->exec($sql);
                $host->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
                $host->commit();
            } catch (Throwable $e) {
                $host->rollBack();
                throw $e;
            }
        }
        $host = null; // close the bootstrap connection; the ATTACH sees the new schema
    }
}

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

        // Single userbase: attach the host database so the shared users
        // table is addressable as host.users in every forum query (SQLite
        // joins across attached databases natively). On a fresh checkout the
        // host db file/schema may not exist yet — ATTACH creates the file,
        // and the bootstrap below applies the host migrations to it.
        $hostPath = forum_host_db_path();
        $hostDir = dirname($hostPath);
        if (!is_dir($hostDir)) {
            mkdir($hostDir, 0775, true);
        }
        $pdo->exec('ATTACH DATABASE ' . $pdo->quote($hostPath) . ' AS host');
        forum_host_db_bootstrap($pdo, $hostPath);

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

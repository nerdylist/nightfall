<?php
/**
 * THE DEAD LAST — web-runnable DB migration runner (production / CloudWays).
 *
 * CloudWays has no sqlite3 CLI, so bin/setup-db.php can't be run from a shell
 * there. This file does the same job as bin/setup-db.php but is triggered by
 * visiting a URL in the browser:
 *
 *     https://<yourdomain>/migrate.php?token=YOURTOKEN
 *
 * It reuses setup-db.php's core loop: a schema_migrations tracking table,
 * glob migrations/*.sql sorted by filename, and a per-file transaction that
 * skips anything already recorded. Migrations 004 and 005 use bare
 * "ALTER TABLE ... ADD COLUMN" with no idempotency guard, so replaying them
 * against a DB that already has those columns HARD-ERRORS — the skip logic is
 * what keeps that from happening.
 *
 * Safety features on top of setup-db.php:
 *   - Token guard: refuses to run without ?token= matching MIGRATE_TOKEN from
 *     .env. Fails closed — if MIGRATE_TOKEN is unset it will not run at all.
 *   - Backup: copies the SQLite file before applying anything.
 *   - Self-healing tracking table: if the DB already has app tables but an
 *     empty schema_migrations (built from schema.sql or a copied DB), it
 *     backfills 001–006 as already-applied so their ALTERs are never replayed.
 *
 * REQUIRES: MIGRATE_TOKEN=<something-secret> in .env (same file setup-db.php
 * / config.php reads). Without it, this script refuses to run.
 *
 * Safe to re-run — already-applied migrations are skipped and a fresh backup
 * is taken each time.
 *
 * SECURITY: after you've migrated production, DELETE this file (or unset
 * MIGRATE_TOKEN in .env) so it can never be triggered again.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: text/plain; charset=utf-8');

// ---------------------------------------------------------------------------
// 1) Token guard (web safety) — fail closed.
// ---------------------------------------------------------------------------
$expectedToken = (string) env('MIGRATE_TOKEN', '');

if ($expectedToken === '') {
    // No token configured — never run unguarded.
    echo "Refusing to run: MIGRATE_TOKEN is not set.\n";
    echo "Add a line to .env, e.g.  MIGRATE_TOKEN=<something-secret>\n";
    echo "then visit  /migrate.php?token=<something-secret>\n";
    exit(1);
}

$providedToken = (string) ($_GET['token'] ?? '');

if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo "Forbidden — valid ?token= required\n";
    exit;
}

// ---------------------------------------------------------------------------
// Bootstrap — same resolution as setup-db.php.
// ---------------------------------------------------------------------------
$dbPath = grave_db_path();
$migrationsDir = __DIR__ . '/migrations';

echo "The Dead Last — web migration runner\n";
echo "DB path: {$dbPath}\n";

// ---------------------------------------------------------------------------
// 2) Backup before any writes.
// ---------------------------------------------------------------------------
if (is_file($dbPath)) {
    $backupPath = $dbPath . '.bak-' . date('Ymd-His');
    if (!copy($dbPath, $backupPath)) {
        echo "ERROR: could not create backup at {$backupPath} — aborting before any writes.\n";
        exit(1);
    }
    echo "Backup: {$backupPath}\n";
} else {
    $backupPath = null;
    echo "Backup: (fresh DB — nothing to back up)\n";
}

$pdo = grave_db();

// ---------------------------------------------------------------------------
// 3) Self-healing tracking table.
// ---------------------------------------------------------------------------

// Ensure the migrations tracking table exists.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        migration  TEXT UNIQUE NOT NULL,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

// Gather already-applied migrations.
$applied = [];
foreach ($pdo->query('SELECT migration FROM schema_migrations') as $row) {
    $applied[$row['migration']] = true;
}

// Gather migration files in order.
$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_STRING);

if (empty($files)) {
    echo "No migration files found in {$migrationsDir}.\n";
    exit(0);
}

// Detect a pre-existing schema whose migrations were never tracked: an empty
// schema_migrations table alongside app tables that only earlier migrations
// could have created. Blindly replaying 001–006 against such a DB would error
// on 004/005's bare ALTERs, so we backfill them as already-applied instead.
$backfillNotice = null;

if (empty($applied)) {
    $probe = $pdo->query(
        "SELECT count(*) AS c FROM sqlite_master
         WHERE type='table' AND name IN ('users','player_stats','characters')"
    )->fetch();
    $preexistingTables = (int) ($probe['c'] ?? 0);

    if ($preexistingTables > 0) {
        // Existing schema, untracked. Mark every migration numbered <= 006 as
        // applied so the loop skips them (parse the leading numeric prefix so
        // future 008/009 files are never affected).
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
            foreach ($files as $file) {
                $name = basename($file);
                $num = (int) $name; // leading numeric prefix, e.g. "004_..." -> 4
                if ($num >= 1 && $num <= 6) {
                    $stmt->execute(['migration' => $name]);
                    $applied[$name] = true;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            echo "ERROR backfilling migration tracking: " . $e->getMessage() . "\n";
            exit(1);
        }

        $backfillNotice = 'Detected existing schema without migration tracking — '
            . 'marking 001-006 as already applied (backfill), will apply 007+ only.';
        echo "\n{$backfillNotice}\n";
    }
}
// If schema_migrations already had rows, trust it and don't backfill.
// If the DB is brand-new (no app tables, empty tracking), don't backfill —
// the loop below applies ALL migrations 001..007 from scratch.

// ---------------------------------------------------------------------------
// 4) Apply loop — identical behavior to setup-db.php.
// ---------------------------------------------------------------------------
$appliedNow = [];
$skipped = [];

foreach ($files as $file) {
    $name = basename($file);

    if (isset($applied[$name])) {
        $skipped[] = $name;
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        echo "ERROR: could not read migration file: {$file}\n";
        exit(1);
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $name]);
        $pdo->commit();
        $appliedNow[] = $name;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "ERROR applying migration {$name}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// 5) Report — plain text, browser-readable.
// ---------------------------------------------------------------------------
echo "\n";

if (!empty($appliedNow)) {
    echo "Applied:\n";
    foreach ($appliedNow as $name) {
        echo "  - {$name}\n";
    }
} else {
    echo "No new migrations to apply.\n";
}

if (!empty($skipped)) {
    echo "Skipped (already applied):\n";
    foreach ($skipped as $name) {
        echo "  - {$name}\n";
    }
}

echo "\nDatabase ready.\n";

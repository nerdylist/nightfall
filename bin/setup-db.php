<?php
/**
 * GRAVE RISING — DB setup / migration runner.
 *
 * Usage: php web/bin/setup-db.php
 *
 * Reads DB_PATH from .env, creates the containing directory and the
 * SQLite file if missing, then applies every migration in web/migrations/
 * that hasn't been recorded in schema_migrations yet, in filename order.
 * Safe to re-run — already-applied migrations are skipped.
 */

require_once __DIR__ . '/../config.php';

$dbPath = grave_db_path();
$dbDir = dirname($dbPath);
$migrationsDir = __DIR__ . '/../migrations';

echo "Grave Rising — DB setup\n";
echo "DB path: {$dbPath}\n";

if (!is_dir($dbDir)) {
    if (!mkdir($dbDir, 0775, true)) {
        fwrite(STDERR, "ERROR: could not create data directory: {$dbDir}\n");
        exit(1);
    }
    echo "Created data directory: {$dbDir}\n";
}

if (!is_writable($dbDir)) {
    fwrite(STDERR, "ERROR: data directory is not writable: {$dbDir}\n");
    exit(1);
}

$existedBefore = is_file($dbPath);

$pdo = grave_db();

if (!$existedBefore) {
    echo "Created new SQLite database file.\n";
} else {
    echo "Using existing SQLite database file.\n";
}

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
        fwrite(STDERR, "ERROR: could not read migration file: {$file}\n");
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
        fwrite(STDERR, "ERROR applying migration {$name}: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\n";

if (!empty($appliedNow)) {
    echo "Applied migrations:\n";
    foreach ($appliedNow as $name) {
        echo "  - {$name}\n";
    }
} else {
    echo "No new migrations to apply.\n";
}

if (!empty($skipped)) {
    echo "Already up to date (skipped):\n";
    foreach ($skipped as $name) {
        echo "  - {$name}\n";
    }
}

echo "\nDatabase ready at {$dbPath}\n";

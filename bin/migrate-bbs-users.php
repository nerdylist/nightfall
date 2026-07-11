<?php
/**
 * THE DEAD LAST — one-time forum userbase migration (dual userbase -> single).
 *
 * Usage: php bin/migrate-bbs-users.php
 *
 * Collapses the forum's private users table (bbs/forum.db) into the host
 * users table (data/graverising.sqlite), which becomes THE userbase:
 *
 *   1. Applies any pending SQL migrations first (runs bin/setup-db.php
 *      inline), so the host users table has the forum columns from
 *      migrations/004_forum_user_columns.sql.
 *   2. Backs up BOTH database files alongside themselves (.bak-<timestamp>).
 *   3. Copies every forum user into host users: rows linked via tdl_user_id
 *      (or matching an existing host email/username) MERGE their forum
 *      columns (display_name, bio, role, status, reputation, join_date,
 *      counters) into that host row; unlinked rows (mock seeds) become new
 *      host rows, keeping their password_hash.
 *   4. Remaps every user reference in forum.db content tables to host ids:
 *      threads.author_id, posts.author_id, chat_messages.author_id,
 *      reactions.user_id (the complete set of user FKs in the schema).
 *   5. Rebuilds those tables without their REFERENCES users(id) clauses
 *      (the parent table is leaving this file), then renames the old forum
 *      users table to users_legacy as an in-place backup.
 *
 * Safe to re-run: a migrated database (users_legacy present, users absent)
 * is detected and the script exits without touching anything. Step 3 is
 * merge-based, so a run that failed after the host copy resumes cleanly.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

// Step 1 — apply pending host SQL migrations (prints its own summary).
require __DIR__ . '/setup-db.php';

echo "\n=== Forum userbase migration (bbs -> host) ===\n";

$host = grave_db();
$hostPath = grave_db_path();

// Confirm 004 landed.
$hostCols = [];
foreach ($host->query('PRAGMA table_info(users)') as $col) {
    $hostCols[$col['name']] = true;
}
if (!isset($hostCols['role'], $hostCols['status'], $hostCols['reputation'])) {
    fwrite(STDERR, "ERROR: host users table is missing the forum columns — did migration 004 apply?\n");
    exit(1);
}

// Resolve the forum database path (bbs/.env DB_FILE, default forum.db).
$bbsDir = dirname(__DIR__) . '/bbs';
$dbFile = 'forum.db';
$bbsEnv = $bbsDir . '/.env';
if (is_file($bbsEnv) && is_readable($bbsEnv)) {
    foreach (file($bbsEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') { continue; }
        $pos = strpos($line, '=');
        if ($pos !== false && trim(substr($line, 0, $pos)) === 'DB_FILE') {
            $value = trim(substr($line, $pos + 1));
            if ($value !== '') { $dbFile = $value; }
            break;
        }
    }
}
$forumPath = $bbsDir . '/' . $dbFile;

if (!is_file($forumPath)) {
    fwrite(STDERR, "ERROR: forum database not found: {$forumPath}\n");
    exit(1);
}

$forum = new PDO('sqlite:' . $forumPath, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Idempotency / partial-state guards.
$tableExists = function (PDO $pdo, string $name): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$name]);
    return $stmt->fetch() !== false;
};
$hasUsers  = $tableExists($forum, 'users');
$hasLegacy = $tableExists($forum, 'users_legacy');

if (!$hasUsers && $hasLegacy) {
    echo "Already migrated (users_legacy present, users absent). Nothing to do.\n";
    exit(0);
}
if (!$hasUsers) {
    fwrite(STDERR, "ERROR: forum database has no users table and no users_legacy — unexpected state.\n");
    exit(1);
}
if ($hasLegacy) {
    fwrite(STDERR, "ERROR: both users and users_legacy exist in forum.db — resolve manually before re-running.\n");
    exit(1);
}

// Step 2 — backups (checkpoint WAL first so the copies are complete).
$forum->exec('PRAGMA wal_checkpoint(TRUNCATE)');
try { $host->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) { /* non-WAL host db */ }
$ts = date('Ymd-His');
foreach ([$hostPath, $forumPath] as $src) {
    $bak = $src . '.bak-' . $ts;
    if (!copy($src, $bak)) {
        fwrite(STDERR, "ERROR: could not back up {$src}\n");
        exit(1);
    }
    echo "Backed up " . basename($src) . " -> " . basename($bak) . "\n";
}

// Step 3 — copy/merge forum users into host users, building old-id -> host-id map.
$forumUsers = $forum->query('SELECT * FROM users ORDER BY id')->fetchAll();
$map = [];
$mergedCount = 0;
$createdCount = 0;

$mergeStmt = $host->prepare(
    'UPDATE users
        SET display_name = :display_name, bio = :bio, role = :role, status = :status,
            reputation = :reputation, join_date = :join_date,
            threads_started = :threads_started, chat_messages = :chat_messages
      WHERE id = :id'
);
$insertStmt = $host->prepare(
    'INSERT INTO users
        (email, username, password_hash, created_at, display_name, bio, role, status,
         reputation, join_date, threads_started, chat_messages)
     VALUES
        (:email, :username, :password_hash, :created_at, :display_name, :bio, :role, :status,
         :reputation, :join_date, :threads_started, :chat_messages)'
);

$host->beginTransaction();
try {
    foreach ($forumUsers as $fu) {
        $targetId = null;

        // Linked shadow row -> its host row.
        if (!empty($fu['tdl_user_id'])) {
            $chk = $host->prepare('SELECT id FROM users WHERE id = ?');
            $chk->execute([(int) $fu['tdl_user_id']]);
            if ($chk->fetch() !== false) {
                $targetId = (int) $fu['tdl_user_id'];
            }
        }
        // Unlinked but a host account already owns this email/username -> merge there.
        if ($targetId === null) {
            $chk = $host->prepare('SELECT id, username, email FROM users WHERE email = :e OR username = :u LIMIT 1');
            $chk->execute([':e' => $fu['email'], ':u' => $fu['username']]);
            $existing = $chk->fetch();
            if ($existing !== false) {
                $targetId = (int) $existing['id'];
                echo "WARNING: forum user #{$fu['id']} ({$fu['username']} <{$fu['email']}>) had no tdl_user_id link; "
                    . "merged into host user #{$targetId} ({$existing['username']} <{$existing['email']}>) by email/username match.\n";
            }
        }

        $forumFields = [
            ':display_name'    => $fu['display_name'],
            ':bio'             => $fu['bio'],
            ':role'            => $fu['role'],
            ':status'          => $fu['status'],
            ':reputation'      => (int) $fu['reputation'],
            ':join_date'       => $fu['join_date'],
            ':threads_started' => (int) $fu['threads_started'],
            ':chat_messages'   => (int) $fu['chat_messages'],
        ];

        if ($targetId !== null) {
            $mergeStmt->execute($forumFields + [':id' => $targetId]);
            $mergedCount++;
        } else {
            $insertStmt->execute($forumFields + [
                ':email'         => $fu['email'],
                ':username'      => $fu['username'],
                ':password_hash' => (string) $fu['password_hash'],
                ':created_at'    => $fu['created_at'] !== null ? $fu['created_at'] : gmdate('c'),
            ]);
            $targetId = (int) $host->lastInsertId();
            $createdCount++;
        }

        $map[(int) $fu['id']] = $targetId;
    }
    $host->commit();
} catch (Throwable $e) {
    $host->rollBack();
    fwrite(STDERR, 'ERROR copying users to host: ' . $e->getMessage() . "\n");
    exit(1);
}
echo "Users copied to host: " . count($map) . " (merged into existing: {$mergedCount}, new host rows: {$createdCount})\n";

// Steps 4 + 5 — remap content references, rebuild tables sans users FKs,
// rename users -> users_legacy. All in one forum-db transaction.
$forum->exec('PRAGMA foreign_keys = OFF');

// Every user-referencing column in the forum schema.
$userRefs = [
    ['threads',       'author_id'],
    ['posts',         'author_id'],
    ['chat_messages', 'author_id'],
    ['reactions',     'user_id'],
];

// Rebuild DDL: identical to the live schema minus REFERENCES users(id).
$rebuilds = [
    'threads' => [
        'ddl' => 'CREATE TABLE threads_new (
                id INTEGER PRIMARY KEY,
                category_id INTEGER NOT NULL REFERENCES categories(id),
                author_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                excerpt TEXT,
                replies INTEGER DEFAULT 0,
                views INTEGER DEFAULT 0,
                pinned INTEGER DEFAULT 0,
                locked INTEGER DEFAULT 0,
                hot INTEGER DEFAULT 0,
                last_activity TEXT,
                created_at TEXT,
                updated_at TEXT
            )',
        'indexes' => ['CREATE INDEX idx_threads_category ON threads(category_id)'],
    ],
    'posts' => [
        'ddl' => 'CREATE TABLE posts_new (
                id INTEGER PRIMARY KEY,
                thread_id INTEGER NOT NULL REFERENCES threads(id),
                author_id INTEGER NOT NULL,
                body TEXT NOT NULL,
                created TEXT,
                created_at TEXT
            )',
        'indexes' => ['CREATE INDEX idx_posts_thread ON posts(thread_id)'],
    ],
    'chat_messages' => [
        'ddl' => 'CREATE TABLE chat_messages_new (
                id INTEGER PRIMARY KEY,
                thread_id INTEGER NOT NULL REFERENCES threads(id),
                author_id INTEGER NOT NULL,
                text TEXT NOT NULL,
                timestamp TEXT,
                created_at TEXT
            )',
        'indexes' => ['CREATE INDEX idx_chat_thread ON chat_messages(thread_id)'],
    ],
    'reactions' => [
        'ddl' => 'CREATE TABLE reactions_new (
                id INTEGER PRIMARY KEY,
                post_id INTEGER REFERENCES posts(id),
                user_id INTEGER NOT NULL,
                emoji TEXT NOT NULL,
                created_at TEXT,
                UNIQUE(post_id, user_id, emoji)
            )',
        'indexes' => ['CREATE INDEX idx_reactions_post ON reactions(post_id)'],
    ],
];

$forum->beginTransaction();
try {
    // 4a. Remap ids via a negate-then-map two-pass so overlapping old/new id
    // ranges can never collide mid-update.
    $remapped = [];
    foreach ($userRefs as [$table, $col]) {
        $forum->exec("UPDATE {$table} SET {$col} = -{$col} WHERE {$col} > 0");
        $upd = $forum->prepare("UPDATE {$table} SET {$col} = :new WHERE {$col} = :negOld");
        foreach ($map as $oldId => $newId) {
            $upd->execute([':new' => $newId, ':negOld' => -$oldId]);
        }
        // Any reference left negative pointed at a nonexistent forum user;
        // restore its absolute value and report it (should be zero).
        $left = (int) $forum->query("SELECT COUNT(*) FROM {$table} WHERE {$col} < 0")->fetchColumn();
        if ($left > 0) {
            $forum->exec("UPDATE {$table} SET {$col} = -{$col} WHERE {$col} < 0");
        }
        $total = (int) $forum->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $remapped[$table] = [$total, $left];
    }

    // 4b/5a. Rebuild content tables without the users foreign keys.
    foreach ($rebuilds as $table => $spec) {
        $forum->exec($spec['ddl']);
        $forum->exec("INSERT INTO {$table}_new SELECT * FROM {$table}");
        $forum->exec("DROP TABLE {$table}");
        $forum->exec("ALTER TABLE {$table}_new RENAME TO {$table}");
        foreach ($spec['indexes'] as $idx) {
            $forum->exec($idx);
        }
    }

    // 5b. Keep the old forum users table as an in-place backup.
    $forum->exec('ALTER TABLE users RENAME TO users_legacy');

    $forum->commit();
} catch (Throwable $e) {
    $forum->rollBack();
    fwrite(STDERR, 'ERROR migrating forum content: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Forum references remapped to host ids:\n";
foreach ($remapped as $table => [$total, $orphans]) {
    echo "  - {$table}: {$total} rows" . ($orphans > 0 ? " (WARNING: {$orphans} unmapped references left as-is)" : '') . "\n";
}
echo "Forum users table renamed to users_legacy (kept as backup).\n";

$hostUserCount = (int) $host->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "\nDone. Host users table now holds {$hostUserCount} users and is the single userbase.\n";

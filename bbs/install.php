<?php
/**
 * Schema installer + seeder for the Nexus forum SQLite database.
 *
 * forum_install(PDO $db): array
 *   - Creates all tables and indexes (IF NOT EXISTS).
 *   - Seeds the mock data set (data/mock.php) once, guarded by an empty
 *     users table.
 *   - Seeds an admin account from .env (idempotent).
 *   - Returns per-table row counts. Produces NO output.
 *
 * When this file is invoked directly (CLI or web), it runs the installer
 * against forum_db() and prints a short plaintext summary. When required by
 * db.php for auto-install, the direct-invocation block does not run.
 */

require_once __DIR__ . '/db.php';

if (!function_exists('forum_install')) {
    /**
     * Create schema, seed mock data + admin, and return row counts.
     *
     * @param PDO $db
     * @return array<string,int>
     */
    function forum_install(PDO $db)
    {
        global $CONFIG;

        // --- Schema ---------------------------------------------------------
        $db->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                display_name TEXT,
                bio TEXT,
                role TEXT NOT NULL DEFAULT 'user',
                status TEXT NOT NULL DEFAULT 'active',
                reputation INTEGER DEFAULT 0,
                join_date TEXT,
                threads_started INTEGER DEFAULT 0,
                chat_messages INTEGER DEFAULT 0,
                created_at TEXT
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                icon TEXT,
                color TEXT,
                featured INTEGER DEFAULT 0,
                sort_order INTEGER DEFAULT 0,
                thread_count INTEGER DEFAULT 0,
                post_count INTEGER DEFAULT 0,
                last_activity TEXT,
                created_at TEXT
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS threads (
                id INTEGER PRIMARY KEY,
                category_id INTEGER NOT NULL REFERENCES categories(id),
                author_id INTEGER NOT NULL REFERENCES users(id),
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
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY,
                thread_id INTEGER NOT NULL REFERENCES threads(id),
                author_id INTEGER NOT NULL REFERENCES users(id),
                body TEXT NOT NULL,
                created TEXT,
                created_at TEXT
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS chat_messages (
                id INTEGER PRIMARY KEY,
                thread_id INTEGER NOT NULL REFERENCES threads(id),
                author_id INTEGER NOT NULL REFERENCES users(id),
                text TEXT NOT NULL,
                timestamp TEXT,
                created_at TEXT
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS reactions (
                id INTEGER PRIMARY KEY,
                post_id INTEGER REFERENCES posts(id),
                user_id INTEGER NOT NULL REFERENCES users(id),
                emoji TEXT NOT NULL,
                created_at TEXT,
                UNIQUE(post_id, user_id, emoji)
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )"
        );

        $db->exec("CREATE INDEX IF NOT EXISTS idx_threads_category ON threads(category_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_posts_thread ON posts(thread_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_chat_thread ON chat_messages(thread_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_reactions_post ON reactions(post_id)");

        // --- Migration: categories.color (idempotent) -----------------------
        // Safe to run on every page load and on an existing populated forum.db.
        $hasColor = false;
        foreach ($db->query('PRAGMA table_info(categories)') as $col) {
            if ($col['name'] === 'color') {
                $hasColor = true;
                break;
            }
        }
        if (!$hasColor) {
            // Column absent -> add it, then run a one-time data fix.
            $db->exec('ALTER TABLE categories ADD COLUMN color TEXT');

            // Map old icon SVG keys to emoji + tasteful colors. Only EXACT key
            // matches are converted, so this never clobbers emoji/URLs and is
            // naturally idempotent (keys won't match after conversion).
            $iconMap = [
                'chat'      => ['💬', '#7a64f5'],
                'megaphone' => ['📣', '#f59e0b'],
                'help'      => ['❓', '#25c2af'],
                'sparkles'  => ['✨', '#ec4899'],
                'compass'   => ['🧭', '#3b82f6'],
                'code'      => ['💻', '#10b981'],
            ];
            $upd = $db->prepare('UPDATE categories SET icon = ?, color = ? WHERE icon = ?');
            foreach ($iconMap as $key => $pair) {
                $upd->execute([$pair[0], $pair[1], $key]);
            }

            // Any category still missing a color gets the accent default so
            // rendering never breaks.
            $db->exec("UPDATE categories SET color = '#7a64f5' WHERE color IS NULL OR color = ''");
        }

        // --- Migration: categories.featured (idempotent) --------------------
        // Safe to run on every page load and on an existing populated forum.db.
        $hasFeatured = false;
        foreach ($db->query('PRAGMA table_info(categories)') as $col) {
            if ($col['name'] === 'featured') {
                $hasFeatured = true;
                break;
            }
        }
        if (!$hasFeatured) {
            // Column absent -> add it, then run a one-time data fix. This only
            // runs in the column-add branch, so it is naturally idempotent.
            $db->exec('ALTER TABLE categories ADD COLUMN featured INTEGER DEFAULT 0');

            $featUpd = $db->prepare('UPDATE categories SET featured = 1 WHERE name = ?');
            foreach (['General Chat', 'Announcements', 'Help & Support'] as $name) {
                $featUpd->execute([$name]);
            }
        }

        // --- Migration: users.tdl_user_id (idempotent) ----------------------
        // SSO shadow-user link to the HOST users table. Safe to run on every
        // page load and on an existing populated forum.db (runs only in the
        // column-add branch). SQLite treats multiple NULLs as distinct, so a
        // UNIQUE index tolerates all the unlinked mock/admin rows.
        $hasTdl = false;
        foreach ($db->query('PRAGMA table_info(users)') as $col) {
            if ($col['name'] === 'tdl_user_id') {
                $hasTdl = true;
                break;
            }
        }
        if (!$hasTdl) {
            $db->exec('ALTER TABLE users ADD COLUMN tdl_user_id INTEGER');
            $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_tdl ON users(tdl_user_id)');
        }

        // --- Seed mock data (guarded by empty users table) ------------------
        $userCount = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();

        if ($userCount === 0) {
            $mock = require __DIR__ . '/data/mock.php';
            $now  = gmdate('c');
            $hash = password_hash('password123', PASSWORD_DEFAULT);

            $db->beginTransaction();

            // Users
            $uStmt = $db->prepare(
                'INSERT INTO users
                    (id, username, email, password_hash, display_name, bio, role, status,
                     reputation, join_date, threads_started, chat_messages, created_at)
                 VALUES
                    (:id, :username, :email, :password_hash, :display_name, :bio, :role, :status,
                     :reputation, :join_date, :threads_started, :chat_messages, :created_at)'
            );
            foreach ($mock['users'] as $u) {
                $uStmt->execute([
                    ':id'              => $u['id'],
                    ':username'        => $u['username'],
                    ':email'           => $u['username'] . '@nexus.test',
                    ':password_hash'   => $hash,
                    ':display_name'    => $u['display_name'],
                    ':bio'             => $u['bio'],
                    ':role'            => 'user',
                    ':status'          => 'active',
                    ':reputation'      => $u['reputation'],
                    ':join_date'       => $u['join_date'],
                    ':threads_started' => $u['threads_started'],
                    ':chat_messages'   => $u['chat_messages'],
                    ':created_at'      => $now,
                ]);
            }

            // Categories
            $cStmt = $db->prepare(
                'INSERT INTO categories
                    (id, name, description, icon, color, featured, sort_order, thread_count, post_count, last_activity, created_at)
                 VALUES
                    (:id, :name, :description, :icon, :color, :featured, :sort_order, :thread_count, :post_count, :last_activity, :created_at)'
            );
            foreach (array_values($mock['categories']) as $i => $c) {
                $cStmt->execute([
                    ':id'            => $c['id'],
                    ':name'          => $c['name'],
                    ':description'   => $c['description'],
                    ':icon'          => $c['icon'],
                    ':color'         => $c['color'],
                    ':featured'      => !empty($c['featured']) ? 1 : 0,
                    ':sort_order'    => $i,
                    ':thread_count'  => $c['thread_count'],
                    ':post_count'    => $c['post_count'],
                    ':last_activity' => $c['last_activity'],
                    ':created_at'    => $now,
                ]);
            }

            // Threads
            $tStmt = $db->prepare(
                'INSERT INTO threads
                    (id, category_id, author_id, title, excerpt, replies, views, pinned, locked, hot, last_activity, created_at, updated_at)
                 VALUES
                    (:id, :category_id, :author_id, :title, :excerpt, :replies, :views, :pinned, :locked, :hot, :last_activity, :created_at, :updated_at)'
            );
            foreach ($mock['threads'] as $t) {
                $tStmt->execute([
                    ':id'            => $t['id'],
                    ':category_id'   => $t['category_id'],
                    ':author_id'     => $t['author_id'],
                    ':title'         => $t['title'],
                    ':excerpt'       => $t['excerpt'],
                    ':replies'       => $t['replies'],
                    ':views'         => $t['views'],
                    ':pinned'        => !empty($t['pinned']) ? 1 : 0,
                    ':locked'        => 0,
                    ':hot'           => !empty($t['hot']) ? 1 : 0,
                    ':last_activity' => $t['last_activity'],
                    ':created_at'    => $now,
                    ':updated_at'    => $now,
                ]);
            }

            // Posts (no explicit id; insert in mock order)
            $pStmt = $db->prepare(
                'INSERT INTO posts
                    (thread_id, author_id, body, created, created_at)
                 VALUES
                    (:thread_id, :author_id, :body, :created, :created_at)'
            );
            foreach ($mock['posts'] as $p) {
                $pStmt->execute([
                    ':thread_id'  => $p['thread_id'],
                    ':author_id'  => $p['author_id'],
                    ':body'       => $p['body'],
                    ':created'    => $p['created'],
                    ':created_at' => $now,
                ]);
            }

            // Chat messages
            $mStmt = $db->prepare(
                'INSERT INTO chat_messages
                    (id, thread_id, author_id, text, timestamp, created_at)
                 VALUES
                    (:id, :thread_id, :author_id, :text, :timestamp, :created_at)'
            );
            foreach ($mock['chat_messages'] as $m) {
                $mStmt->execute([
                    ':id'         => $m['id'],
                    ':thread_id'  => $m['thread_id'],
                    ':author_id'  => $m['author_id'],
                    ':text'       => $m['text'],
                    ':timestamp'  => $m['timestamp'],
                    ':created_at' => $now,
                ]);
            }

            $db->commit();
        }

        // --- Seed admin (separate idempotent guard) -------------------------
        $adminStmt = $db->prepare(
            'SELECT COUNT(*) FROM users WHERE email = :email OR username = :username'
        );
        $adminStmt->execute([
            ':email'    => $CONFIG['ADMIN_EMAIL'],
            ':username' => $CONFIG['ADMIN_USERNAME'],
        ]);
        $adminExists = (int) $adminStmt->fetchColumn();

        if ($adminExists === 0) {
            $ins = $db->prepare(
                'INSERT INTO users
                    (username, email, password_hash, display_name, role, status,
                     reputation, join_date, threads_started, chat_messages, created_at)
                 VALUES
                    (:username, :email, :password_hash, :display_name, :role, :status,
                     :reputation, :join_date, :threads_started, :chat_messages, :created_at)'
            );
            $ins->execute([
                ':username'      => $CONFIG['ADMIN_USERNAME'],
                ':email'         => $CONFIG['ADMIN_EMAIL'],
                ':password_hash' => password_hash($CONFIG['ADMIN_PASSWORD'], PASSWORD_DEFAULT),
                ':display_name'  => 'Admin',
                ':role'          => 'admin',
                ':status'        => 'active',
                ':reputation'    => 0,
                ':join_date'     => date('Y-m-d'),
                ':threads_started' => 0,
                ':chat_messages' => 0,
                ':created_at'    => gmdate('c'),
            ]);
        }

        // --- Seed "Latest News" feed category (idempotent) ------------------
        // Powers the host site's /api/feed home-page news feed. Guarded by the
        // category name, so it never duplicates on subsequent page loads.
        $newsStmt = $db->prepare('SELECT id FROM categories WHERE name = ?');
        $newsStmt->execute(['Latest News']);
        $newsCatId = $newsStmt->fetchColumn();

        if ($newsCatId === false) {
            $db->beginTransaction();

            $maxSort = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories')->fetchColumn();
            $db->prepare(
                'INSERT INTO categories
                    (name, description, icon, color, featured, sort_order, thread_count, post_count, last_activity, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, 0, 0, NULL, ?)'
            )->execute([
                'Latest News',
                'Official announcements, patch notes, and dev updates from the team.',
                '📰',
                '#f59e0b',
                $maxSort + 1,
                gmdate('c'),
            ]);
            $newsCatId = (int) $db->lastInsertId();

            // Author: prefer an admin, fall back to the lowest user id.
            $authorId = $db->query(
                "SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1"
            )->fetchColumn();
            if ($authorId === false) {
                $authorId = $db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
            }
            $authorId = (int) $authorId;

            // Demo news threads, newest first ([title, excerpt, body, daysAgo,
            // replies, views]). Staggered created_at dates make ordering visible.
            $newsThreads = [
                [
                    'Patch 0.9.4 — The Rot Update Is Live',
                    'Dynamic infection spread, two new weapon families, and a full rebalance of melee stamina costs.',
                    "Patch 0.9.4 is rolling out to all servers now.\n\nHighlights:\n- Dynamic infection spread: bites now matter. Untreated wounds attract the horde.\n- New weapon families: improvised spears and the Willard Arms bolt-action line.\n- Melee stamina costs rebalanced across the board.\n- Fixed the barricade duplication exploit in Colton Row.\n\nFull notes on the wiki. See you out there, survivors.",
                    1, 12, 348,
                ],
                [
                    'Server Stress Test — Sign-Ups Open Until Friday',
                    'Help us break the servers before launch does. 5,000 slots, wave two starts this weekend.',
                    "Wave two of the server stress test kicks off this weekend and sign-ups are open until Friday night.\n\nWe are targeting 5,000 concurrent survivors across three regions. Everyone who participates gets the \"First Responder\" radio skin at launch.\n\nSign up from your account page. Bring friends. Bring bandages.",
                    3, 27, 512,
                ],
                [
                    'Developer Update #8: Horde AI Overhaul',
                    'The dead are learning. A deep dive into the new horde director, sound propagation, and door memory.',
                    "This month the AI team rebuilt the horde director from scratch.\n\nZombies now share a sound-propagation map: fire a gun in the church district and expect visitors for the next ten minutes. They also remember doors. If a walker saw you slam one, the horde will pile on it instead of wandering off.\n\nNext update we will talk about the new stealth indicators.",
                    5, 19, 431,
                ],
                [
                    'Blood Moon Event Announced for August',
                    'One night. Triple hordes. Permadeath rewards. The first seasonal event arrives next month.',
                    "Our first seasonal event, Blood Moon, lands in August.\n\nFor one in-game night the sky turns red, hordes triple in size, and every kill counts toward town-wide rewards. Survivors who make it to sunrise without dying earn the exclusive Blood Moon flare gun.\n\nDates and server schedule coming in the next dev update.",
                    8, 34, 689,
                ],
                [
                    'New Map Teaser: Harlow Falls',
                    'A flooded mill town north of Colton. First look at the drowned district and the rope-bridge crossings.',
                    "Time for a first look at Harlow Falls, the flooded mill town coming north of Colton.\n\nHalf the town sits underwater. You will cross the drowned district by rooftop and rope bridge while the current drags debris — and worse — through the streets below.\n\nMore screenshots next week. The waterlogged walkers are our favorite thing we have ever shipped.",
                    10, 22, 566,
                ],
                [
                    'Community Spotlight: Best Base Builds of June',
                    'The diner fortress, the church bell early-warning rig, and more of your best builds this month.',
                    "The June community spotlight is here.\n\nThis month's winners include the Last Stop Diner fortress (double kill-corridor entrance), a church bell early-warning system rigged to tripwires, and a rooftop garden that somehow survived three horde nights.\n\nDrop your builds in this thread for a shot at the July spotlight.",
                    13, 41, 720,
                ],
            ];

            $tIns = $db->prepare(
                'INSERT INTO threads
                    (category_id, author_id, title, excerpt, replies, views, pinned, locked, hot, last_activity, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)'
            );
            $pIns = $db->prepare(
                'INSERT INTO posts (thread_id, author_id, body, created, created_at)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $latest = null;
            foreach ($newsThreads as $n) {
                [$title, $excerpt, $body, $daysAgo, $replies, $views] = $n;
                $when = gmdate('c', time() - ($daysAgo * 86400) - 3600 * ($daysAgo % 5));
                if ($latest === null) {
                    $latest = $when;
                }
                $tIns->execute([$newsCatId, $authorId, $title, $excerpt, $replies, $views, $when, $when, $when]);
                $threadId = (int) $db->lastInsertId();
                $pIns->execute([$threadId, $authorId, $body, $when, $when]);
            }

            $db->prepare(
                'UPDATE categories SET thread_count = ?, post_count = ?, last_activity = ? WHERE id = ?'
            )->execute([count($newsThreads), count($newsThreads), $latest, $newsCatId]);

            $db->commit();
        }
        $newsCatId = (int) $newsCatId;

        // --- Seed feed_sections setting (idempotent) ------------------------
        // JSON mapping of host-site section keys -> forum categories, consumed
        // by /api/feed and managed in Keeper > Settings. Only inserted when
        // the key is absent, so admin edits are never overwritten.
        $hasFeedSections = $db->prepare('SELECT COUNT(*) FROM settings WHERE key = ?');
        $hasFeedSections->execute(['feed_sections']);

        if ((int) $hasFeedSections->fetchColumn() === 0) {
            $db->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute([
                'feed_sections',
                json_encode([
                    [
                        'key'         => 'latest-news',
                        'label'       => 'Latest News',
                        'category_id' => $newsCatId,
                        'limit'       => 4,
                    ],
                ]),
            ]);
        }

        // --- Row counts -----------------------------------------------------
        $counts = [];
        foreach (['users', 'categories', 'threads', 'posts', 'chat_messages', 'reactions'] as $table) {
            $counts[$table] = (int) $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }

        return $counts;
    }
}

// Direct-invocation block: only when install.php is the entry point.
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__
) {
    $counts = forum_install(forum_db());
    echo "Nexus forum install complete.\n";
    foreach ($counts as $table => $n) {
        echo "  {$table}: {$n}\n";
    }
}

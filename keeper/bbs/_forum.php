<?php
/**
 * Shared helper for the Keeper > Forum admin pages (keeper/bbs/*).
 *
 * The forum content tables (categories, threads, posts, chat_messages,
 * reactions) live in bbs/forum.db. Author/user names come from the HOST users
 * table. This connector opens the forum DB and ATTACHes the host DB as `host`
 * — exactly like the forum's own forum_db() does — so every `JOIN host.users`
 * query ports over unchanged. Keeper never includes bbs/ code; this is a
 * self-contained direct PDO (same approach as keeper/settings.php's
 * keeper_forum_db(), plus the host attach).
 */

if (!function_exists('keeper_bbs_db')) {
    function keeper_bbs_db(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $forumPath = __DIR__ . '/../../bbs/forum.db';
        $pdo = new PDO('sqlite:' . $forumPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // Attach the host DB as `host` so author joins (JOIN host.users) work.
        $hostPath = grave_db_path();
        $pdo->exec('ATTACH DATABASE ' . $pdo->quote($hostPath) . ' AS host');

        return $pdo;
    }
}

/** Keeper-scoped CSRF token (shared by the forum admin pages). */
if (!function_exists('keeper_bbs_csrf')) {
    function keeper_bbs_csrf(): string
    {
        if (empty($_SESSION['keeper_csrf'])) {
            $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['keeper_csrf'];
    }
}

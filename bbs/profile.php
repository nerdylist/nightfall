<?php
/**
 * RETIRED (Boss 2026-07-25): the forum profile page is superseded by the
 * public profile at /u/{username}. This stub 301s old links/bookmarks there
 * permanently. The route stays in routes.json so /bbs/profile/:user keeps
 * resolving; delete both together if ever fully removed.
 */
if (isset($GLOBALS['_ROUTE_PARAMS']['user']) && !isset($_GET['user'])) {
    $_GET['user'] = $GLOBALS['_ROUTE_PARAMS']['user'];
}

$user = trim((string) ($_GET['user'] ?? ''));

// Numeric ids (old-style ?user=7 links) resolve to the username first.
if ($user !== '' && preg_match('/^\d+$/', $user)) {
    require __DIR__ . '/config.php';
    require_once __DIR__ . '/db.php';
    $pdo = new PDO('sqlite:' . forum_host_db_path());
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([(int) $user]);
    $name = $stmt->fetchColumn();
    $user = $name !== false ? (string) $name : '';
}

header('Location: /u/' . rawurlencode($user), true, 301);
exit;

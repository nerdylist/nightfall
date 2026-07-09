<?php
require_once __DIR__ . '/config.php';

// SSO: destroys the shared PHPSESSID, logging the user out of BOTH the host
// and the forum (/bbs). Accepts GET or POST. Mirrors the forum's
// auth_logout() cookie-expiry pattern so the cookie is actually cleared.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Location: /');
exit;

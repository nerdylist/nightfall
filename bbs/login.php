<?php
require __DIR__ . '/config.php';

// SSO: identity lives on the HOST. This page is a thin handoff — no form.
// Forward a safe ?next (local path only) to the host /login.php so the user
// lands back in the forum after logging in.
if (!function_exists('login_safe_next')) {
    function login_safe_next($next) {
        $next = (string) $next;
        if ($next === '') return '';
        if ($next[0] !== '/') return '';        // must be root-relative
        if (strpos($next, '//') === 0) return ''; // no scheme-relative
        if (strpos($next, "\\") !== false) return '';
        if (strpos($next, ':') !== false) return '';
        return $next;
    }
}

$next = login_safe_next($_GET['next'] ?? '');
if ($next === '') { $next = '/bbs/index.php'; }

header('Location: /login?next=' . urlencode($next));
exit;

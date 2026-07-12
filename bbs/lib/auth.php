<?php
require_once __DIR__ . '/../db.php';

if (!function_exists('auth_start_session')) {
    // Shared-session (SSO) contract: host (root) and forum (/bbs) share ONE
    // PHP session cookie. Sharing requires the SAME cookie NAME (default
    // PHPSESSID) and PATH ('/'). Whichever app calls session_start() first
    // wins — so if a session is already active we do nothing (the host uses a
    // bare session_start(), and re-setting params after the fact would be a
    // no-op anyway). When the forum is the first to start it, we set params
    // that keep the cookie scoped to path '/' so the host reads the same one.
    function auth_start_session() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => true,
        ]);
        session_start();
    }
}

if (!function_exists('auth_current_user')) {
    // Single-userbase resolver: the host users table (attached by forum_db()
    // as host.users) IS the forum userbase, so $_SESSION['user_id'] — set by
    // the host login — is the forum user id directly. No shadow rows, no
    // provisioning. Banned users resolve to null. Cached per request; NULL
    // display_name/join_date fall back to username/date(created_at) so rows
    // created by plain host registration render everywhere.
    function auth_current_user($forceReload = false) {
        static $cache = [];
        if ($forceReload) {
            $cache = [];
            return null;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $id = (int) $_SESSION['user_id'];
        if (array_key_exists($id, $cache)) {
            return $cache[$id];
        }

        $stmt = forum_db()->prepare(
            "SELECT id, username, email,
                    COALESCE(NULLIF(display_name, ''), username) AS display_name,
                    COALESCE(bio, '') AS bio,
                    role, status, reputation,
                    COALESCE(join_date, date(created_at)) AS join_date,
                    threads_started, chat_messages, created_at
             FROM host.users
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row === false || $row['status'] === 'banned') {
            // Stale session (user gone) or banned account.
            $cache[$id] = null;
            return null;
        }
        $row['id'] = (int) $row['id'];
        $cache[$id] = $row;
        return $row;
    }
}

if (!function_exists('auth_is_logged_in')) {
    function auth_is_logged_in() {
        return auth_current_user() !== null;
    }
}

if (!function_exists('auth_is_admin')) {
    function auth_is_admin() {
        $u = auth_current_user();
        return $u !== null && $u['role'] === 'admin' && $u['status'] === 'active';
    }
}

if (!function_exists('auth_logout')) {
    function auth_logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        auth_current_user(true);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
    }
}

if (!function_exists('csrf_check')) {
    function csrf_check($token) {
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('require_login')) {
    function require_login() {
        if (!auth_is_logged_in()) {
            header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/index.php'));
            exit;
        }
    }
}

if (!function_exists('require_admin')) {
    function require_admin() {
        // Admin access is granted to any logged-in user whose host account has
        // role='admin' (auth_is_admin() checks role==='admin' && status==='active').
        // Auth is unified on the single main-site login (/login); there is no
        // separate Keeper credential anymore.
        if (!auth_is_admin()) {
            if (!auth_is_logged_in()) {
                header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/index.php'));
                exit;
            }
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}

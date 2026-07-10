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

if (!function_exists('auth_host_db')) {
    // Dedicated read/write connection to the HOST (THE DEAD LAST) SQLite db.
    // The host owns identity (users: id, email, username, password_hash). We
    // resolve its DB_PATH from the host .env without pulling in host config.php
    // (which redefines env()/db helpers). Cached in a static; no side effects.
    function auth_host_db() {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }
        $hostRoot = dirname(__DIR__, 2); // bbs/lib -> bbs -> host root
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
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}

if (!function_exists('auth_current_user')) {
    // SSO shadow-user resolver. $_SESSION['user_id'] is the HOST user id (set
    // by the host login). We map it to a bbs "shadow" row (users.tdl_user_id =
    // host id) so forum content FKs keep working. Find-or-create + sync on
    // every request; cached by host id. Returns the same column shape pages
    // expect (plus tdl_user_id).
    function auth_current_user($forceReload = false) {
        static $cache = [];
        if ($forceReload) {
            $cache = [];
            return null;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $hostId = (int) $_SESSION['user_id'];
        if (array_key_exists($hostId, $cache)) {
            return $cache[$hostId];
        }

        // Load the host identity row.
        $hStmt = auth_host_db()->prepare(
            'SELECT id, email, username FROM users WHERE id = :id'
        );
        $hStmt->execute([':id' => $hostId]);
        $host = $hStmt->fetch();
        if ($host === false) {
            // Stale session (host user gone).
            $cache[$hostId] = null;
            return null;
        }

        $db = forum_db();
        $cols = 'id, username, email, display_name, bio, role, status, reputation, join_date, threads_started, chat_messages, created_at, tdl_user_id';

        // Find shadow row by host id.
        $sStmt = $db->prepare('SELECT ' . $cols . ' FROM users WHERE tdl_user_id = :tid');
        $sStmt->execute([':tid' => $hostId]);
        $row = $sStmt->fetch();

        if ($row !== false) {
            if ($row['status'] === 'banned') {
                $cache[$hostId] = null;
                return null;
            }
            // Sync host-owned fields (username/email) if they drifted; leave
            // bbs-owned fields (display_name, bio, role, status, counters)
            // untouched. Both are UNIQUE in bbs, so pre-check each against a
            // DIFFERENT row and SKIP the sync on collision — never let the
            // UPDATE throw (e.g. an admin whose host email equals the seeded
            // bbs admin's email would otherwise crash every /bbs/ request).
            $shadowId = (int) $row['id'];
            $sets = [];
            $params = [':id' => $shadowId];
            if ($row['username'] !== $host['username']) {
                $uChk = $db->prepare('SELECT 1 FROM users WHERE username = :u AND id <> :id LIMIT 1');
                $uChk->execute([':u' => $host['username'], ':id' => $shadowId]);
                if ($uChk->fetch() === false) {
                    $sets[] = 'username = :u';
                    $params[':u'] = $host['username'];
                    $row['username'] = $host['username'];
                }
            }
            if ($row['email'] !== $host['email']) {
                $eChk = $db->prepare('SELECT 1 FROM users WHERE email = :e AND id <> :id LIMIT 1');
                $eChk->execute([':e' => $host['email'], ':id' => $shadowId]);
                if ($eChk->fetch() === false) {
                    $sets[] = 'email = :e';
                    $params[':e'] = $host['email'];
                    $row['email'] = $host['email'];
                }
            }
            if (!empty($sets)) {
                $upd = $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
                $upd->execute($params);
            }
            $row['id'] = (int) $row['id'];
            $row['tdl_user_id'] = (int) $row['tdl_user_id'];
            $cache[$hostId] = $row;
            return $row;
        }

        // Provision a new shadow row.
        // Username must be UNIQUE in bbs; deterministically suffix if taken by
        // a different/NULL tdl_user_id row.
        $baseName = (string) $host['username'];
        $username = $baseName;
        $chk = $db->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
        $n = 1;
        while (true) {
            $chk->execute([':u' => $username]);
            if ($chk->fetch() === false) { break; }
            $n++;
            $username = $baseName . $n;
        }

        // Email is UNIQUE too; prefer the real host email, fall back to a
        // synthetic unique one only on collision so the INSERT never fails.
        $email = (string) $host['email'];
        $eChk = $db->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
        $eChk->execute([':e' => $email]);
        if ($eChk->fetch() !== false) {
            $email = $baseName . $hostId . '@tdl.local';
        }

        // Admin if host email matches the forum's configured admin email.
        global $CONFIG;
        $role = 'user';
        if (isset($CONFIG['ADMIN_EMAIL'])
            && strtolower(trim((string) $host['email'])) === strtolower(trim((string) $CONFIG['ADMIN_EMAIL']))) {
            $role = 'admin';
        }

        $now = date('c');
        $ins = $db->prepare(
            'INSERT INTO users
                (username, email, password_hash, display_name, bio, role, status,
                 reputation, join_date, threads_started, chat_messages, created_at, tdl_user_id)
             VALUES
                (:u, :e, \'\', :dn, \'\', :role, \'active\', 0, :jd, 0, 0, :ca, :tid)'
        );
        $ins->execute([
            ':u'    => $username,
            ':e'    => $email,
            ':dn'   => $baseName,
            ':role' => $role,
            ':jd'   => $now,
            ':ca'   => $now,
            ':tid'  => $hostId,
        ]);

        $sStmt->execute([':tid' => $hostId]);
        $row = $sStmt->fetch();
        $row['id'] = (int) $row['id'];
        $row['tdl_user_id'] = (int) $row['tdl_user_id'];
        $cache[$hostId] = $row;
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

if (!function_exists('auth_login')) {
    function auth_login($identifier, $password) {
        $identifier = trim((string) $identifier);
        $stmt = forum_db()->prepare(
            'SELECT id, username, email, password_hash, display_name, bio, role, status, reputation, join_date, threads_started, chat_messages, created_at FROM users WHERE email = :id OR username = :id LIMIT 1'
        );
        $stmt->execute([':id' => $identifier]);
        $row = $stmt->fetch();
        if ($row === false || !password_verify((string) $password, $row['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid credentials.'];
        }
        if ($row['status'] === 'banned') {
            return ['success' => false, 'error' => 'This account has been banned.'];
        }
        $_SESSION['user_id'] = (int) $row['id'];
        session_regenerate_id(true);
        auth_current_user(true);
        return ['success' => true, 'error' => null];
    }
}

if (!function_exists('auth_register')) {
    function auth_register($username, $email, $password, $confirm, $displayName = null) {
        $errors = [];
        $username = trim((string) $username);
        $email = trim((string) $email);
        $password = (string) $password;
        $confirm = (string) $confirm;
        $displayName = $displayName === null ? null : trim((string) $displayName);

        if ($username === '') { $errors[] = 'Username is required.'; }
        if ($email === '') { $errors[] = 'Email is required.'; }
        if ($password === '') { $errors[] = 'Password is required.'; }
        if ($confirm === '') { $errors[] = 'Please confirm your password.'; }

        if ($username !== '' && !(strlen($username) >= 3 && strlen($username) <= 30 && preg_match('/^[A-Za-z0-9_]+$/', $username))) {
            $errors[] = 'Username must be 3-30 characters: letters, numbers, underscore.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if ($password !== '' && strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== '' && $confirm !== '' && $password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if ($username !== '') {
            $stmt = forum_db()->prepare('SELECT 1 FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            if ($stmt->fetch() !== false) {
                $errors[] = 'That username is taken.';
            }
        }
        if ($email !== '') {
            $stmt = forum_db()->prepare('SELECT 1 FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            if ($stmt->fetch() !== false) {
                $errors[] = 'That email is already registered.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('c');
        $dn = ($displayName !== null && trim((string) $displayName) !== '') ? trim((string) $displayName) : $username;
        $stmt = forum_db()->prepare(
            'INSERT INTO users (username, email, password_hash, display_name, bio, role, status, reputation, join_date, threads_started, chat_messages, created_at) VALUES (:u, :e, :h, :dn, \'\', \'user\', \'active\', 0, :jd, 0, 0, :ca)'
        );
        $stmt->execute([
            ':u' => $username,
            ':e' => $email,
            ':h' => $hash,
            ':dn' => $dn,
            ':jd' => $now,
            ':ca' => $now,
        ]);
        $_SESSION['user_id'] = (int) forum_db()->lastInsertId();
        session_regenerate_id(true);
        auth_current_user(true);
        return ['success' => true, 'errors' => []];
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
        // Keeper SSO: the host Keeper admin (keeper/index.php sets
        // $_SESSION['keeper_admin'] on the same shared PHPSESSID/'/' session
        // cookie) is trusted as a forum admin — no host user account needed.
        // Admin pages never reference the current user's identity (only
        // partials/header.php does, and it null-checks), so no stand-in user
        // row is required.
        if (!empty($_SESSION['keeper_admin'])) {
            return;
        }
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

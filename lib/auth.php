<?php
/**
 * GRAVE RISING — shared auth logic (register / login / tokens).
 *
 * Used by both the JSON API (web/api/) and the existing UI pages
 * (register.php, login.php, keeper/) so the rules live in one place.
 */

require_once __DIR__ . '/db.php';

/**
 * Validate registration input. Returns an array of field => message
 * errors (empty array = valid).
 */
function grave_validate_registration(string $email, string $username, string $password): array
{
    $errors = [];

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if ($username === '' || strlen($username) < 3 || strlen($username) > 32) {
        $errors['username'] = 'Username must be 3-32 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = 'Username may only contain letters, numbers, and underscores.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    return $errors;
}

/**
 * Create a new user. Throws grave_DuplicateError if email/username taken.
 * Returns the new user's id.
 */
function grave_create_user(PDO $pdo, string $email, string $username, string $password): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email OR username = :username');
    $stmt->execute(['email' => $email, 'username' => $username]);
    if ($stmt->fetch()) {
        throw new GraveDuplicateError('An account with that email or username already exists.');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO users (email, username, password_hash) VALUES (:email, :username, :password_hash)'
    );
    $stmt->execute(['email' => $email, 'username' => $username, 'password_hash' => $hash]);

    // Single userbase: this row IS the forum user too. Forum columns
    // (role/status/reputation/counters) carry schema defaults, and the forum
    // falls back to username/date(created_at) for display_name/join_date at
    // read time — nothing to provision.
    return (int) $pdo->lastInsertId();
}

/**
 * Verify username-or-email + password. Returns the user row (without
 * password_hash) on success, or null on failure.
 */
function grave_verify_login(PDO $pdo, string $identifier, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :id OR username = :id LIMIT 1');
    $stmt->execute(['id' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    unset($user['password_hash']);
    return $user;
}

/**
 * Generate and store a new API auth token for a user. Returns the token.
 */
function grave_issue_token(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare('INSERT INTO tokens (token, user_id) VALUES (:token, :user_id)');
    $stmt->execute(['token' => $token, 'user_id' => $userId]);

    return $token;
}

function grave_public_user(array $user): array
{
    return [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
    ];
}

/**
 * Resolve the currently logged-in host user from the session. Returns the
 * user row (id, username, email, role, status) or null when nobody is logged
 * in, the session references a deleted account, or the account is banned — a
 * banned user is not "logged in" for gating purposes (mirrors the forum's
 * auth_current_user). Cached per request. Assumes config.php already started
 * the session; does not call session_start().
 */
if (!function_exists('grave_current_user')) {
    function grave_current_user(): ?array
    {
        static $cache = [];

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        $id = (int) $_SESSION['user_id'];
        if (array_key_exists($id, $cache)) {
            return $cache[$id];
        }

        $stmt = grave_db()->prepare(
            'SELECT id, username, email, role, status FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] === 'banned') {
            // Stale session (user gone) or banned account.
            $cache[$id] = null;
            return null;
        }

        $user['id'] = (int) $user['id'];
        $cache[$id] = $user;
        return $user;
    }
}

/**
 * True only when an active admin is logged in. Host-owned so admin gating
 * never depends on bbs/ code (Keeper never includes bbs/).
 */
if (!function_exists('grave_is_admin')) {
    function grave_is_admin(): bool
    {
        $user = grave_current_user();
        return $user !== null && $user['role'] === 'admin' && $user['status'] === 'active';
    }
}

class GraveDuplicateError extends RuntimeException {}

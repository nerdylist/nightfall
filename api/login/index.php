<?php
/**
 * GRAVE RISING — API: POST /api/login
 *
 * Request JSON: { "identifier": "username-or-email", "password": "..." }
 * Success (200): { "success": true, "user": {id,username,email}, "token": "..." }
 * Error (401): { "success": false, "error": "..." }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$input = grave_read_json_input();

$identifier = trim((string) ($input['identifier'] ?? ($input['username'] ?? ($input['email'] ?? ''))));
$password = (string) ($input['password'] ?? '');

if ($identifier === '' || $password === '') {
    grave_json_response(400, ['success' => false, 'error' => 'Username/email and password are required.']);
}

$pdo = grave_db();

$user = grave_verify_login($pdo, $identifier, $password);
if (!$user) {
    grave_json_response(401, ['success' => false, 'error' => 'Invalid credentials.']);
}

$token = grave_issue_token($pdo, (int) $user['id']);

grave_json_response(200, [
    'success' => true,
    'user' => grave_public_user($user),
    'token' => $token,
]);

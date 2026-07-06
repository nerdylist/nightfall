<?php
/**
 * GRAVE RISING — API: POST /api/register
 *
 * Request JSON: { "email": "...", "username": "...", "password": "..." }
 * Success (201): { "success": true, "user": {id,username,email}, "token": "..." }
 * Error: { "success": false, "error": "..." } with 400/409/422.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$input = grave_read_json_input();

$email = trim((string) ($input['email'] ?? ''));
$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');

$errors = grave_validate_registration($email, $username, $password);
if (!empty($errors)) {
    grave_json_response(422, ['success' => false, 'error' => 'Validation failed.', 'fields' => $errors]);
}

$pdo = grave_db();

try {
    $userId = grave_create_user($pdo, $email, $username, $password);
} catch (GraveDuplicateError $e) {
    grave_json_response(409, ['success' => false, 'error' => $e->getMessage()]);
}

$stmt = $pdo->prepare('SELECT id, email, username FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();

$token = grave_issue_token($pdo, $userId);

grave_json_response(201, [
    'success' => true,
    'user' => grave_public_user($user),
    'token' => $token,
]);

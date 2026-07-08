<?php
/**
 * GRAVE RISING — Meshy webhook receiver.
 *
 * This is the URL registered in the Meshy dashboard as the account webhook
 * (MESHY_PAYLOAD_URL = https://graverising.com/mesh_payload.php). Meshy POSTs
 * the task object JSON here whenever an image-to-3d or rigging task changes
 * state. We authenticate with MESHY_WEBHOOK_SECRET (shared secret in the URL
 * ?secret=..., or an HMAC signature header if Meshy ever sends one), then
 * upsert the task into the meshy_tasks table for the local puller to collect.
 *
 * Must respond with an HTTP status < 400 on success or Meshy will retry and
 * eventually auto-disable the webhook.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/meshy.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$raw = $raw === false ? '' : $raw;

if (!meshy_verify_webhook($raw)) {
    // Never reveal why (no secret echo). Generic 401.
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

$task = json_decode($raw, true);
if (!is_array($task)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

// Meshy delivers the task object directly; tolerate a wrapping envelope too.
if (isset($task['data']) && is_array($task['data']) && isset($task['data']['id'])) {
    $task = $task['data'];
}

try {
    $pdo = grave_db();
    $taskId = meshy_store_task($pdo, $task);
} catch (Throwable $e) {
    // Log server-side only; do not leak internals to Meshy.
    error_log('[mesh_payload] store failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Storage error.']);
    exit;
}

if ($taskId === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload missing task id.']);
    exit;
}

http_response_code(200);
echo json_encode(['success' => true]);

<?php
/**
 * GRAVE RISING — API: Meshy backlog puller (local machine only).
 *
 * Auth: Authorization: Bearer <MESHY_WEBHOOK_SECRET>
 *
 * GET  /api/meshy            -> list SUCCEEDED, not-yet-consumed tasks as JSON:
 *      { "success": true, "tasks": [ { task_id, task_type, status, payload }, ... ] }
 *      The local meshy-queue.sh reads model_urls/texture_urls straight from
 *      each task's payload to download + assemble the {name}_{m|f}.zip.
 *
 * POST /api/meshy            -> mark tasks consumed once downloaded:
 *      body { "consume": ["<task_id>", ...] }
 *      -> { "success": true, "consumed": <count> }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/meshy.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if (!meshy_verify_bearer()) {
    grave_json_response(401, ['success' => false, 'error' => 'Unauthorized.']);
}

$pdo = grave_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->query(
        "SELECT task_id, task_type, status, progress, payload, received_at, updated_at
         FROM meshy_tasks
         WHERE status = 'SUCCEEDED' AND consumed_at IS NULL
         ORDER BY updated_at ASC"
    );

    $tasks = [];
    foreach ($stmt->fetchAll() as $row) {
        $tasks[] = [
            'task_id'    => $row['task_id'],
            'task_type'  => $row['task_type'],
            'status'     => $row['status'],
            'progress'   => (int) $row['progress'],
            'payload'    => json_decode($row['payload'], true),
            'updated_at' => $row['updated_at'],
        ];
    }

    grave_json_response(200, ['success' => true, 'tasks' => $tasks]);
}

if ($method === 'POST') {
    $input = grave_read_json_input();
    $ids = $input['consume'] ?? [];
    if (!is_array($ids)) {
        $ids = [];
    }

    $consumed = 0;
    $stmt = $pdo->prepare(
        'UPDATE meshy_tasks SET consumed_at = CURRENT_TIMESTAMP
         WHERE task_id = :task_id AND consumed_at IS NULL'
    );
    foreach ($ids as $id) {
        $id = (string) $id;
        if ($id === '') {
            continue;
        }
        $stmt->execute(['task_id' => $id]);
        $consumed += $stmt->rowCount();
    }

    grave_json_response(200, ['success' => true, 'consumed' => $consumed]);
}

grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);

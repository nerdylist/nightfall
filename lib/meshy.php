<?php
/**
 * GRAVE RISING — shared Meshy backlog helpers.
 *
 * Used by the webhook receiver (mesh_payload.php) and the local puller
 * endpoint (api/meshy/pull). Keeps the shared-secret auth + the task
 * upsert logic in one place.
 *
 * Auth model
 * ----------
 * Meshy's public docs do NOT define an HMAC webhook signature scheme
 * (webhooks are configured account-level in the dashboard and must simply
 * respond < 400). So authenticity is enforced with the user-supplied
 * MESHY_WEBHOOK_SECRET two ways, whichever the caller provides:
 *
 *   1. Shared secret in the request (query ?secret=... or an
 *      X-Meshy-Secret header) — embed the secret in the webhook URL you
 *      register in the Meshy dashboard. This is the primary path.
 *   2. HMAC-SHA256 of the raw body in a signature header
 *      (X-Meshy-Signature / Meshy-Signature) — verified defensively in
 *      case Meshy ever starts signing. Optional; ignored if absent.
 *
 * The SAME secret guards the local pull endpoint (Bearer token).
 * All comparisons are constant-time. The secret is NEVER echoed.
 */

require_once __DIR__ . '/db.php';

/** Fetch the configured webhook secret, or '' if unset. */
function meshy_secret(): string
{
    return (string) env('MESHY_WEBHOOK_SECRET', '');
}

/**
 * Verify an inbound webhook request against MESHY_WEBHOOK_SECRET.
 * Returns true if EITHER the shared-secret (query/header) matches OR a
 * valid HMAC-SHA256 signature header is present and correct.
 *
 * @param string $rawBody raw request body (for HMAC verification)
 */
function meshy_verify_webhook(string $rawBody): bool
{
    $secret = meshy_secret();
    if ($secret === '') {
        return false; // misconfigured server — fail closed
    }

    // 1. Shared secret via query param or header.
    $provided = (string) ($_GET['secret']
        ?? ($_SERVER['HTTP_X_MESHY_SECRET'] ?? ''));
    if ($provided !== '' && hash_equals($secret, $provided)) {
        return true;
    }

    // 2. Optional HMAC-SHA256 signature over the raw body.
    $sigHeader = (string) ($_SERVER['HTTP_X_MESHY_SIGNATURE']
        ?? ($_SERVER['HTTP_MESHY_SIGNATURE'] ?? ''));
    if ($sigHeader !== '') {
        // tolerate "sha256=<hex>" or a bare hex digest
        $sig = str_contains($sigHeader, '=')
            ? substr($sigHeader, strpos($sigHeader, '=') + 1)
            : $sigHeader;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (hash_equals($expected, strtolower(trim($sig)))) {
            return true;
        }
    }

    return false;
}

/**
 * Verify the local puller's Bearer token against MESHY_WEBHOOK_SECRET.
 * The puller sends `Authorization: Bearer <secret>`.
 */
function meshy_verify_bearer(): bool
{
    $secret = meshy_secret();
    if ($secret === '') {
        return false;
    }

    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    // Some FastCGI setups expose it under a redirect-prefixed name.
    if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
        return $token !== '' && hash_equals($secret, $token);
    }

    return false;
}

/**
 * Upsert a Meshy task object (from a webhook payload) into meshy_tasks.
 * Keyed on task_id; latest status/payload wins. Does NOT clear an existing
 * consumed_at (a late duplicate webhook must not resurrect a consumed task).
 *
 * @param array $task decoded task object (must contain an "id")
 * @return string|null the task_id stored, or null if the payload had no id
 */
function meshy_store_task(PDO $pdo, array $task): ?string
{
    $taskId = (string) ($task['id'] ?? '');
    if ($taskId === '') {
        return null;
    }

    $type     = (string) ($task['type'] ?? ($task['task_type'] ?? ''));
    $status   = (string) ($task['status'] ?? '');
    $progress = (int) ($task['progress'] ?? 0);
    $payload  = json_encode($task);

    $stmt = $pdo->prepare(
        'INSERT INTO meshy_tasks (task_id, task_type, status, progress, payload, updated_at)
         VALUES (:task_id, :task_type, :status, :progress, :payload, CURRENT_TIMESTAMP)
         ON CONFLICT(task_id) DO UPDATE SET
             task_type  = excluded.task_type,
             status     = excluded.status,
             progress   = excluded.progress,
             payload    = excluded.payload,
             updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'task_id'   => $taskId,
        'task_type' => $type,
        'status'    => $status,
        'progress'  => $progress,
        'payload'   => $payload,
    ]);

    return $taskId;
}

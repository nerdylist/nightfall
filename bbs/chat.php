<?php
/**
 * Live-chat endpoint.
 *
 * Serves the live chat for a thread. A GET request reads the messages for a
 * thread (open to everyone, optionally filtered to ids after a cursor); a POST
 * request appends a new message from an authenticated user with a valid CSRF
 * token. This endpoint always emits JSON and never outputs HTML.
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();
require_once __DIR__ . '/partials/avatar.php';
require_once __DIR__ . '/data/db.php';

header('Content-Type: application/json');

// Emit a JSON error with the given HTTP status, then stop.
if (!function_exists('chat_fail')) {
    function chat_fail($status, $message) {
        http_response_code($status);
        echo json_encode(['error' => $message]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $threadId = (int) ($_GET['thread_id'] ?? 0);
    $afterId  = (int) ($_GET['after_id'] ?? 0);

    if ($threadId <= 0) {
        chat_fail(400, 'Missing thread_id.');
    }

    $messages = get_chat_for_thread($threadId);

    // Index users by id once for quick author lookups.
    $usersById = [];
    foreach (get_users() as $u) {
        $usersById[(int) $u['id']] = $u;
    }

    $out = [];
    foreach ($messages as $m) {
        if ((int) $m['id'] <= $afterId) {
            continue;
        }
        $author = $usersById[(int) $m['author_id']] ?? null;
        $authorName = (string) ($author['display_name'] ?? 'Unknown');
        $out[] = [
            'id'              => (int) $m['id'],
            'author_id'       => (int) $m['author_id'],
            'author_name'     => $authorName,
            'author_initials' => forum_avatar_initials($authorName),
            'text'            => $m['text'],
            'timestamp'       => $m['timestamp'],
        ];
    }

    http_response_code(200);
    echo json_encode(['messages' => $out]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!auth_is_logged_in()) {
        chat_fail(401, 'Authentication required.');
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_check($token)) {
        chat_fail(403, 'Invalid CSRF token.');
    }

    $threadId = (int) ($_POST['thread_id'] ?? 0);
    if ($threadId <= 0) {
        chat_fail(400, 'Missing thread_id.');
    }
    if (get_thread($threadId) === null) {
        chat_fail(404, 'Thread not found.');
    }

    $text = trim((string) ($_POST['text'] ?? ''));
    if ($text === '') {
        chat_fail(400, 'Message is empty.');
    }
    if (mb_strlen($text) > 1000) {
        chat_fail(400, 'Message too long.');
    }

    $me = auth_current_user();

    try {
        $id = create_chat_message($threadId, (int) $me['id'], $text);
    } catch (InvalidArgumentException $e) {
        chat_fail(400, $e->getMessage());
    } catch (Throwable $e) {
        chat_fail(500, 'Could not save message.');
    }

    // Fetch the stored row back so the client renders the canonical values.
    $stored = null;
    foreach (get_chat_for_thread($threadId) as $m) {
        if ((int) $m['id'] === $id) {
            $stored = $m;
            break;
        }
    }

    $author_name     = (string) ($me['display_name'] ?? 'You');
    $author_initials = forum_avatar_initials($author_name);

    http_response_code(200);
    echo json_encode([
        'id'              => $id,
        'author_id'       => (int) $me['id'],
        'author_name'     => $author_name,
        'author_initials' => $author_initials,
        'text'            => $stored['text'] ?? $text,
        'timestamp'       => $stored['timestamp'] ?? '',
    ]);
    exit;
}

chat_fail(405, 'Method not allowed.');

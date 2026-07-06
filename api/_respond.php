<?php
/**
 * GRAVE RISING — shared JSON API helpers (response + request parsing).
 * Prefixed with underscore so it's not itself a routable endpoint.
 */

/**
 * Read the request body as JSON, falling back to form-encoded $_POST
 * if the body isn't valid JSON (tolerate both for the Unity client).
 */
function grave_read_json_input(): array
{
    $raw = file_get_contents('php://input');

    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return $_POST;
}

/**
 * Send a JSON response with the given HTTP status code and stop execution.
 */
function grave_json_response(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body);
    exit;
}

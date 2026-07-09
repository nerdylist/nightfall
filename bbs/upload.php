<?php
/**
 * Image upload endpoint.
 *
 * Accepts a single image file via POST (field name "image") from an
 * authenticated user with a valid CSRF token, validates it by real MIME
 * type and size, stores it under the configured upload directory with an
 * unguessable hashed filename, and returns a JSON response. This endpoint
 * always emits JSON and never outputs HTML.
 */

require __DIR__ . '/config.php';
require_once __DIR__ . '/lib/auth.php';
auth_start_session();

header('Content-Type: application/json');

// Emit a JSON error with the given HTTP status, then stop.
if (!function_exists('upload_fail')) {
    function upload_fail($status, $message) {
        http_response_code($status);
        echo json_encode(['error' => $message]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    upload_fail(405, 'Method not allowed.');
}

if (!auth_is_logged_in()) {
    upload_fail(401, 'Authentication required.');
}

$token = $_POST['csrf_token'] ?? '';
if (!csrf_check($token)) {
    upload_fail(403, 'Invalid CSRF token.');
}

if (!isset($_FILES['image'])) {
    upload_fail(400, 'No file uploaded.');
}

if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    upload_fail(400, 'Upload failed.');
}

if (!is_uploaded_file($_FILES['image']['tmp_name'])) {
    upload_fail(400, 'Invalid upload.');
}

if ($_FILES['image']['size'] > $CONFIG['UPLOAD_MAX_BYTES']) {
    upload_fail(400, 'File too large.');
}

$tmp_name = $_FILES['image']['tmp_name'];

// Determine the real MIME type; never trust the client-supplied extension or type.
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($tmp_name);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowed[$mime])) {
    upload_fail(400, 'Unsupported file type.');
}

$ext = $allowed[$mime];

$info = getimagesize($tmp_name);
if ($info === false || empty($info)) {
    upload_fail(400, 'Invalid image.');
}

$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$dir = __DIR__ . '/' . $CONFIG['UPLOAD_DIR'];
$dest = $dir . '/' . $filename;

if (!move_uploaded_file($tmp_name, $dest)) {
    upload_fail(500, 'Could not save file.');
}

http_response_code(200);
echo json_encode(['url' => '/bbs/' . $CONFIG['UPLOAD_DIR'] . '/' . $filename]);
exit;

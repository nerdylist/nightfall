<?php
/**
 * Keeper entry point. Auth is unified — admins log in at the main /login as a
 * user with role='admin'. This shim routes: admin -> dashboard, logged-in
 * non-admin -> home, logged-out -> /login.
 */
require_once __DIR__ . '/../config.php';

if (grave_is_admin()) {
    header('Location: /keeper/dashboard.php');
    exit;
}

if (grave_current_user() !== null) {
    // Logged in but not an admin — no business in Keeper.
    header('Location: /');
    exit;
}

header('Location: /login?next=/keeper/dashboard.php');
exit;

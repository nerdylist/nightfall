<?php
// SSO: logout is host-owned (shared session). Hand off to the host endpoint,
// which destroys the shared PHPSESSID for both host and forum.
header('Location: /logout.php');
exit;

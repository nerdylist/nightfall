<?php
require __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../partials/avatar.php';

auth_start_session();
require_admin();

if (!function_exists('adm_e')) {
    function adm_e($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
}

if (!function_exists('adm_flash')) {
    function adm_flash($type, $msg) { $_SESSION['admin_flash'] = ['type' => $type, 'msg' => $msg]; }
}

if (!function_exists('adm_redirect')) {
    function adm_redirect($to) { header('Location: ' . $to); exit; }
}
<?php
if (!isset($CONFIG)) { require __DIR__ . '/../config.php'; }
$BASE = $BASE ?? '';
require_once __DIR__ . '/../lib/auth.php';
auth_start_session();
$currentUser = auth_current_user();

// Current forum URI, for round-tripping back here after SSO login.
$navNext = $_SERVER['REQUEST_URI'] ?? '/bbs/';

// Configure the shared nav partial for the forum context, then render it.
$NAV_HIDE_HOME = true;
$NAV_ADMIN_URL = ($currentUser !== null && auth_is_admin()) ? $BASE . 'admin/' : null;
$NAV_LOGIN_URL = '/bbs/login.php?next=' . urlencode($navNext);
$NAV_REGISTER_URL = '/bbs/register.php';
$NAV_SEARCH_PLACEHOLDER = 'Search ' . $CONFIG['SITE_NAME'] . '...';
require __DIR__ . '/../../partials/nav.php';

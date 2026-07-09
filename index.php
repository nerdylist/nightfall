<?php
// THE DEAD LAST — friendly-URL front controller.
// Caddy serves real files (*.php, api/*, keeper/*, assets) directly; only
// paths with no matching file fall back here for friendly-URL routing.
// Routed :params are exposed by Router as the global $_ROUTE_PARAMS; the
// three bbs pages that need them (thread/category/profile) bridge that into
// $_GET at their top (see each page's "friendly-URL" comment).
require_once __DIR__ . '/router/Router.php';

$router = new Router(__DIR__);
$router->loadRoutesFromJson(__DIR__ . '/router/routes.json');
$router->setNotFoundHandler(__DIR__ . '/404.php');
$router->route();

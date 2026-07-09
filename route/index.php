<?php
// Universal Router Demo
// Load the router
require_once __DIR__ . '/router/Router.php';

// Create router instance with project root path
$router = new Router(__DIR__);

// Load routes from JSON config
$router->loadRoutesFromJson(__DIR__ . '/router/routes.json');

// Process the request
$router->route();

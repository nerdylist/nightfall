<?php
/**
 * Universal URL Router
 *
 * A simple, portable routing system for clean URLs
 * No dependencies, no .htaccess required - pure PHP solution
 *
 * Usage: Include this file at the top of your main entry point (e.g., index.php)
 */

class Router {
    private $routes = [];
    private $basePath = '';
    private $notFoundHandler = null;
    private $projectRoot = '';

    /**
     * Initialize router with project root path
     *
     * @param string $projectRoot Absolute path to project root
     */
    public function __construct($projectRoot = null) {
        $this->projectRoot = $projectRoot ?: dirname(__DIR__);
    }

    /**
     * Load routes from JSON configuration file
     *
     * @param string $configPath Path to routes.json file
     * @return Router Returns self for method chaining
     */
    public function loadRoutesFromJson($configPath) {
        if (!file_exists($configPath)) {
            throw new Exception("Routes configuration file not found: {$configPath}");
        }

        $jsonContent = file_get_contents($configPath);
        $config = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in routes configuration: " . json_last_error_msg());
        }

        if (isset($config['base_path'])) {
            $this->basePath = rtrim($config['base_path'], '/');
        }

        if (isset($config['routes']) && is_array($config['routes'])) {
            foreach ($config['routes'] as $route) {
                if (isset($route['url']) && isset($route['destination'])) {
                    $this->addRoute($route['url'], $route['destination']);
                }
            }
        }

        return $this;
    }

    /**
     * Add a single route
     *
     * @param string $url The clean URL pattern
     * @param string $destination The actual file to load
     * @return Router Returns self for method chaining
     */
    public function addRoute($url, $destination) {
        $url = '/' . trim($url, '/');
        if ($url === '/') {
            $url = '/';
        }
        $this->routes[$url] = $destination;
        return $this;
    }

    /**
     * Set custom 404 handler
     *
     * @param callable|string $handler Function or file path
     * @return Router Returns self for method chaining
     */
    public function setNotFoundHandler($handler) {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /**
     * Process the current request and route to appropriate destination
     *
     * @return void
     */
    public function route() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $requestUri = strtok($requestUri, '?');

        // Remove base path if set
        if ($this->basePath && strpos($requestUri, $this->basePath) === 0) {
            $requestUri = substr($requestUri, strlen($this->basePath));
        }

        // Normalize the URL
        $requestUri = '/' . trim($requestUri, '/');
        if ($requestUri === '/') {
            $requestUri = '/';
        }

        // Check for exact match
        if (isset($this->routes[$requestUri])) {
            $this->executeRoute($this->routes[$requestUri]);
            return;
        }

        // Check for pattern matches (supports :param syntax)
        foreach ($this->routes as $pattern => $destination) {
            $regex = $this->patternToRegex($pattern);
            if (preg_match($regex, $requestUri, $matches)) {
                // Remove numeric keys, keep only named params
                $params = array_filter($matches, function($key) {
                    return !is_numeric($key);
                }, ARRAY_FILTER_USE_KEY);

                $this->executeRoute($destination, $params);
                return;
            }
        }

        // No route found - trigger 404
        $this->handleNotFound($requestUri);
    }

    /**
     * Convert route pattern to regex
     *
     * @param string $pattern Route pattern
     * @return string Regex pattern
     */
    private function patternToRegex($pattern) {
        // Escape forward slashes
        $pattern = str_replace('/', '\/', $pattern);

        // Convert :param to named capture groups
        $pattern = preg_replace('/\:([a-zA-Z0-9_]+)/', '(?P<$1>[^\/]+)', $pattern);

        return '/^' . $pattern . '$/';
    }

    /**
     * Execute a route by loading the destination file
     *
     * @param string $destination File path
     * @param array $params URL parameters
     */
    private function executeRoute($destination, $params = []) {
        // Make params globally available as $_ROUTE_PARAMS
        global $_ROUTE_PARAMS;
        $_ROUTE_PARAMS = $params;

        // Try absolute path first
        if (file_exists($destination)) {
            require $destination;
            exit;
        }

        // Try relative to project root
        $projectPath = rtrim($this->projectRoot, '/') . '/' . ltrim($destination, '/');
        if (file_exists($projectPath)) {
            require $projectPath;
            exit;
        }

        // Try relative to router directory
        $routerPath = __DIR__ . '/' . ltrim($destination, '/');
        if (file_exists($routerPath)) {
            require $routerPath;
            exit;
        }

        // File not found
        throw new Exception("Route destination file not found: {$destination}");
    }

    /**
     * Handle 404 - Not Found
     *
     * @param string $uri The requested URI
     */
    private function handleNotFound($uri) {
        http_response_code(404);

        if ($this->notFoundHandler) {
            if (is_callable($this->notFoundHandler)) {
                call_user_func($this->notFoundHandler, $uri);
            } elseif (is_string($this->notFoundHandler) && file_exists($this->notFoundHandler)) {
                require $this->notFoundHandler;
            } else {
                echo $this->defaultNotFoundPage($uri);
            }
        } else {
            echo $this->defaultNotFoundPage($uri);
        }
        exit;
    }

    /**
     * Default 404 page
     *
     * @param string $uri The requested URI
     * @return string HTML content
     */
    private function defaultNotFoundPage($uri) {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #0a0a0a;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            font-size: 6rem;
            margin: 0;
            color: #fff;
            text-shadow: 0 0 20px rgba(255,255,255,0.1);
        }
        p {
            font-size: 1.2rem;
            color: #888;
        }
        code {
            background: #1a1a1a;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>Page not found: <code>{$uri}</code></p>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get all registered routes
     *
     * @return array
     */
    public function getRoutes() {
        return $this->routes;
    }
}

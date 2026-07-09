# Universal PHP Router

A portable, dependency-free URL routing system for PHP projects. Converts ugly URLs to clean, friendly URLs without requiring .htaccess or web server configuration.

## What It Does

Transforms URLs:
- `https://site.com/some-page.php` → `https://site.com/some-page`
- `https://site.com/blog-post.php?id=5` → `https://site.com/blog/my-post`
- `https://site.com/product.php?id=123` → `https://site.com/products/123`

## How It Works

This is a **pure PHP routing solution**. Instead of using .htaccess rewrites, you include the router at the top of a single entry point file (index.php in your project root). All requests go through this file, and the router decides which page to load based on the URL.

## Files

- **Router.php** - The routing engine (pure PHP class)
- **routes.json** - Configuration file mapping URLs to destination files
- **CLAUDE.md** - This documentation file

## Installation

### Step 1: Copy the router folder

Copy the entire `router/` folder into your project:

```
your-project/
├── router/
│   ├── Router.php
│   ├── routes.json
│   └── CLAUDE.md
├── index.php (create this - see below)
├── pages/
│   ├── home.php
│   ├── about.php
│   └── blog-post.php
└── ... (other files)
```

### Step 2: Create index.php entry point

Create `index.php` in your project root:

```php
<?php
// Load the router
require_once 'router/Router.php';

// Create router instance with project root path
$router = new Router(__DIR__);

// Load routes from JSON config
$router->loadRoutesFromJson(__DIR__ . '/router/routes.json');

// Optional: Set custom 404 handler
// $router->setNotFoundHandler('pages/404.php');

// Process the request
$router->route();
```

That's it! The router will now handle all requests.

### Step 3: Configure your routes

Edit `router/routes.json`:

```json
{
  "base_path": "",
  "routes": [
    {
      "url": "/",
      "destination": "pages/home.php",
      "description": "Homepage"
    },
    {
      "url": "/about",
      "destination": "pages/about.php",
      "description": "About page"
    },
    {
      "url": "/blog/:slug",
      "destination": "pages/blog-post.php",
      "description": "Blog post with dynamic slug"
    }
  ]
}
```

### Step 4: Create your page files

Create the PHP files referenced in your routes:

**pages/home.php:**
```php
<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
</head>
<body>
    <h1>Welcome Home</h1>
</body>
</html>
```

**pages/blog-post.php:**
```php
<?php
// Access route parameters
global $_ROUTE_PARAMS;
$slug = $_ROUTE_PARAMS['slug'] ?? 'unknown';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Post: <?= htmlspecialchars($slug) ?></title>
</head>
<body>
    <h1>Blog Post: <?= htmlspecialchars($slug) ?></h1>
</body>
</html>
```

## Configuration

### routes.json Structure

```json
{
  "base_path": "",
  "routes": [
    {
      "url": "/your-clean-url",
      "destination": "path/to/file.php",
      "description": "Optional description"
    }
  ]
}
```

- **url**: The clean URL pattern visitors will see
- **destination**: Path to the PHP file to load (relative to project root)
- **description**: Optional note for documentation
- **base_path**: Optional subdirectory if your site isn't at domain root

### Dynamic URL Parameters

Use `:paramname` syntax to capture parts of the URL:

```json
{
  "url": "/blog/:slug",
  "destination": "pages/blog-post.php"
}
```

```json
{
  "url": "/products/:id",
  "destination": "pages/product.php"
}
```

Access parameters in your destination file:

```php
<?php
global $_ROUTE_PARAMS;
$slug = $_ROUTE_PARAMS['slug'] ?? null;
$id = $_ROUTE_PARAMS['id'] ?? null;
```

### Custom 404 Handler

Set a custom 404 page in your index.php:

```php
// Use a file
$router->setNotFoundHandler('pages/404.php');

// Or use a callback
$router->setNotFoundHandler(function($uri) {
    echo "<h1>Page not found: {$uri}</h1>";
});
```

## Route Matching Order

1. **Exact matches** are checked first (`/about`)
2. **Pattern matches** with parameters (`/blog/:slug`)
3. **404 handler** if nothing matches

## Usage Examples

### Example 1: Simple Pages

```json
{
  "routes": [
    {"url": "/", "destination": "pages/home.php"},
    {"url": "/about", "destination": "pages/about.php"},
    {"url": "/contact", "destination": "pages/contact.php"}
  ]
}
```

### Example 2: Blog with Dynamic URLs

```json
{
  "routes": [
    {"url": "/blog", "destination": "pages/blog-list.php"},
    {"url": "/blog/:slug", "destination": "pages/blog-post.php"}
  ]
}
```

### Example 3: E-commerce Site

```json
{
  "routes": [
    {"url": "/", "destination": "pages/home.php"},
    {"url": "/products", "destination": "pages/products.php"},
    {"url": "/products/:id", "destination": "pages/product-detail.php"},
    {"url": "/cart", "destination": "pages/cart.php"},
    {"url": "/checkout", "destination": "pages/checkout.php"}
  ]
}
```

## Serving Static Files

CSS, JavaScript, and image files are served directly by your web server (Caddy/Apache/nginx). Only PHP routing goes through index.php.

Your Caddyfile might look like:

```
yoursite.test {
    root * /path/to/project
    encode gzip

    # Serve static files directly
    @static {
        path *.css *.js *.png *.jpg *.gif *.svg *.woff *.woff2
    }
    file_server @static

    # Route everything else through PHP
    php_fastcgi 127.0.0.1:9000
    file_server
}
```

## Portability

To move this router to a new project:

1. Copy the `router/` folder
2. Create `index.php` entry point (3 lines of code)
3. Edit `router/routes.json` with your routes
4. Done!

No web server configuration needed. No dependencies. Just PHP.

## Troubleshooting

**Routes not working:**
- Verify `routes.json` is valid JSON
- Check destination file paths are correct relative to project root
- Ensure index.php exists and loads the router

**Parameters not available:**
- Use `global $_ROUTE_PARAMS;` at the top of your destination file
- Check the parameter name matches the `:name` in your route URL

**404 for everything:**
- Verify index.php is being called
- Check routes.json syntax
- Ensure web server is routing requests to index.php

**Static files (CSS/JS) not loading:**
- Check file paths in your HTML
- Ensure web server is configured to serve static files directly

## Web Server Configuration

### Caddy (Recommended for this setup)

Your Caddyfile should route non-file requests to index.php:

```
yoursite.test {
    root * /path/to/project
    encode gzip
    php_fastcgi 127.0.0.1:9000
    file_server
    log { level ERROR }
}
```

This configuration automatically routes through index.php when a file doesn't exist.

### Apache

If using Apache, create `.htaccess` in project root:

```apache
RewriteEngine On
RewriteBase /

# Serve existing files directly
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Route everything else to index.php
RewriteRule ^ index.php [QSA,L]
```

### nginx

Add to your server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## Advanced Usage

### Programmatic Routes

Add routes via code instead of JSON:

```php
$router = new Router(__DIR__);
$router->addRoute('/', 'pages/home.php');
$router->addRoute('/about', 'pages/about.php');
$router->route();
```

### Method Chaining

All methods support chaining:

```php
$router = new Router(__DIR__);
$router
    ->loadRoutesFromJson(__DIR__ . '/router/routes.json')
    ->setNotFoundHandler('pages/404.php')
    ->route();
```

## Security

- Always sanitize `$_ROUTE_PARAMS` before using in queries or output
- Use `htmlspecialchars()` when displaying parameters
- Don't expose internal file paths in route destinations
- Validate parameters against expected formats

## Performance

This router is lightweight and fast:
- No database queries
- Simple regex matching
- Minimal overhead
- Single file include

## License

Free to use in any project. No attribution required.

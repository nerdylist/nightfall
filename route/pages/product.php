<?php
// Access route parameters
global $_ROUTE_PARAMS;
$id = $_ROUTE_PARAMS['id'] ?? 'unknown';

// Simulate some product data
$products = [
    '123' => [
        'name' => 'Ultra-Dark Theme Pack',
        'price' => '$49.99',
        'description' => 'A complete dark theme package for modern web applications. Includes color schemes, component styles, and design tokens.',
        'features' => [
            'ShadCN-inspired design system',
            'Ultra-dark color palette',
            'Responsive components',
            'Clean and modern aesthetics'
        ]
    ],
    '456' => [
        'name' => 'Router Pro License',
        'price' => '$99.99',
        'description' => 'Professional license for the Universal PHP Router with premium support and advanced features.',
        'features' => [
            'Lifetime updates',
            'Priority support',
            'Advanced caching',
            'Middleware support'
        ]
    ],
    '789' => [
        'name' => 'PHP Starter Kit',
        'price' => '$29.99',
        'description' => 'Everything you need to start building modern PHP applications with clean architecture.',
        'features' => [
            'Router system included',
            'Database utilities',
            'Authentication templates',
            'Admin dashboard'
        ]
    ]
];

// Get product data or use defaults
$product = $products[$id] ?? [
    'name' => 'Product #' . $id,
    'price' => '$0.00',
    'description' => 'This is a dynamically generated product page based on the URL parameter. In a real application, you would fetch product details from a database using the ID parameter.',
    'features' => [
        'Dynamic routing demonstration',
        'URL parameter extraction',
        'Clean URL structure',
        'Portable routing system'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Products</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Universal Router Demo</h1>
            <nav>
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
                <a href="/blog">Blog</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <h2><?= htmlspecialchars($product['name']) ?></h2>
            <p><?= htmlspecialchars($product['price']) ?></p>
        </div>

        <div class="content-card">
            <h3>Product Description</h3>
            <p><?= htmlspecialchars($product['description']) ?></p>
        </div>

        <div class="content-card">
            <h3>Features</h3>
            <?php foreach ($product['features'] as $feature): ?>
                <p>✓ <?= htmlspecialchars($feature) ?></p>
            <?php endforeach; ?>
        </div>

        <div class="content-card">
            <h3>Route Information</h3>
            <p>This page was accessed via the dynamic route:</p>
            <div class="param-badge">/products/<?= htmlspecialchars($id) ?></div>
            <p style="margin-top: 1rem;">Which matched the route pattern:</p>
            <div class="param-badge">/products/:id → pages/product.php</div>
            <p style="margin-top: 1rem;">The captured parameter:</p>
            <div class="param-badge">$_ROUTE_PARAMS['id'] = "<?= htmlspecialchars($id) ?>"</div>
        </div>

        <div class="content-card">
            <h3>Try Other Products</h3>
            <div class="product-grid">
                <div class="product-card">
                    <h4>Product #123</h4>
                    <p>Ultra-Dark Theme Pack</p>
                    <a href="/products/123">View Details</a>
                </div>
                <div class="product-card">
                    <h4>Product #456</h4>
                    <p>Router Pro License</p>
                    <a href="/products/456">View Details</a>
                </div>
                <div class="product-card">
                    <h4>Product #789</h4>
                    <p>PHP Starter Kit</p>
                    <a href="/products/789">View Details</a>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h3>Dynamic Parameter Demo</h3>
            <p>Try changing the ID in the URL to any number:</p>
            <div class="code-block">
                <code>
                    /products/1<br>
                    /products/999<br>
                    /products/abc123<br>
                </code>
            </div>
            <p style="margin-top: 1rem;">The router will capture whatever you put after /products/ and make it available as $_ROUTE_PARAMS['id']</p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

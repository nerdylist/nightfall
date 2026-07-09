<?php
// Access route parameters
global $_ROUTE_PARAMS;
$slug = $_ROUTE_PARAMS['slug'] ?? 'unknown';

// Simulate some blog post data
$posts = [
    'getting-started' => [
        'title' => 'Getting Started with the Router',
        'content' => 'Setting up the universal router is incredibly simple. Just copy the router folder into your project, create an index.php file that loads the router, and configure your routes in routes.json. That\'s it! No complex setup, no dependencies, no framework required.'
    ],
    'dynamic-parameters' => [
        'title' => 'Understanding Dynamic Parameters',
        'content' => 'Dynamic parameters allow you to capture parts of the URL and use them in your page logic. Use the :paramname syntax in your route pattern, and access the value via $_ROUTE_PARAMS[\'paramname\']. This is perfect for blog posts, product pages, user profiles, and any content that needs a unique identifier in the URL.'
    ],
    'clean-urls' => [
        'title' => 'Why Clean URLs Matter',
        'content' => 'Clean URLs improve SEO, user experience, and make your site look more professional. Instead of /page.php?id=5&category=news, you can have /news/5 or even better /news/article-title. Search engines prefer clean URLs, users can remember them easier, and they\'re much more shareable on social media.'
    ],
    'portable-routing' => [
        'title' => 'Portable Routing Solution',
        'content' => 'The beauty of this router is its portability. Copy the router folder to any PHP project and it works immediately. The routes.json configuration keeps all your routes in one place, making it easy to understand and modify your site structure. No need to hunt through .htaccess files or framework configurations.'
    ],
    'no-dependencies' => [
        'title' => 'No Dependencies, Pure PHP',
        'content' => 'This router is built with pure PHP - no Composer packages, no frameworks, no external dependencies. This means zero maintenance burden, no version conflicts, and it works on any PHP 7+ server. Just copy the files and go. The entire router is a single class file that you can read and understand in minutes.'
    ]
];

// Get post data or use defaults
$post = $posts[$slug] ?? [
    'title' => ucwords(str_replace('-', ' ', $slug)),
    'content' => 'This is a dynamically generated blog post based on the URL slug. In a real application, you would fetch this content from a database using the slug parameter.'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Blog</title>
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
                <a href="/blog" class="active">Blog</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <h2><?= htmlspecialchars($post['title']) ?></h2>
            <p>Dynamic blog post routing demo</p>
        </div>

        <div class="content-card">
            <h3>Post Content</h3>
            <p><?= htmlspecialchars($post['content']) ?></p>
        </div>

        <div class="content-card">
            <h3>Route Information</h3>
            <p>This page was accessed via the dynamic route:</p>
            <div class="param-badge">/blog/<?= htmlspecialchars($slug) ?></div>
            <p style="margin-top: 1rem;">Which matched the route pattern:</p>
            <div class="param-badge">/blog/:slug → pages/blog-post.php</div>
            <p style="margin-top: 1rem;">The captured parameter:</p>
            <div class="param-badge">$_ROUTE_PARAMS['slug'] = "<?= htmlspecialchars($slug) ?>"</div>
        </div>

        <div class="content-card">
            <h3>How This Works</h3>
            <p>When you visit /blog/<?= htmlspecialchars($slug) ?>, the router:</p>
            <div class="code-block">
                <code>
                    1. Matches the URL against the pattern /blog/:slug<br>
                    2. Extracts "<?= htmlspecialchars($slug) ?>" as the slug parameter<br>
                    3. Loads pages/blog-post.php<br>
                    4. Makes the parameter available via $_ROUTE_PARAMS['slug']<br>
                    5. This page uses that value to display content
                </code>
            </div>
            <p style="margin-top: 1rem;">In a real application, you would use this slug to query a database and fetch the actual post content.</p>
        </div>

        <div class="content-card">
            <p><a href="/blog" style="color: #fff; text-decoration: none;">&larr; Back to Blog</a></p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Universal Router Demo</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Universal Router Demo</h1>
            <nav>
                <a href="/">Home</a>
                <a href="/about" class="active">About</a>
                <a href="/contact">Contact</a>
                <a href="/blog">Blog</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <h2>About This Router</h2>
            <p>A simple, portable routing solution for PHP projects</p>
        </div>

        <div class="content-card">
            <h3>How It Works</h3>
            <p>This router uses pure PHP to handle URL routing without requiring .htaccess or complex server configuration.</p>
            <p>All requests go through index.php, which loads the Router class and processes the URL against a JSON configuration file.</p>
        </div>

        <div class="content-card">
            <h3>Features</h3>
            <p><strong>JSON Configuration:</strong> Define all your routes in a simple routes.json file.</p>
            <p><strong>Dynamic Parameters:</strong> Support for URL parameters like :slug and :id that are automatically captured and made available to your destination pages.</p>
            <p><strong>No Dependencies:</strong> Pure PHP solution with zero external dependencies.</p>
            <p><strong>Portable:</strong> Copy the router folder to any project and it just works.</p>
            <p><strong>Custom 404 Handler:</strong> Set your own 404 page or callback function.</p>
        </div>

        <div class="content-card">
            <h3>Installation</h3>
            <p>Getting started is simple:</p>
            <div class="code-block">
                <code>
                    1. Copy router/ folder to your project<br>
                    2. Create index.php that loads the router<br>
                    3. Configure routes.json with your URLs<br>
                    4. Create your page files<br>
                    5. Done!
                </code>
            </div>
        </div>

        <div class="content-card">
            <h3>Example Routes</h3>
            <p>Here are the routes configured for this demo:</p>
            <div class="code-block">
                <code>
                    "/" → pages/home.php<br>
                    "/about" → pages/about.php<br>
                    "/contact" → pages/contact.php<br>
                    "/blog" → pages/blog.php<br>
                    "/blog/:slug" → pages/blog-post.php<br>
                    "/products/:id" → pages/product.php
                </code>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

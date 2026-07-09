<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Universal Router Demo</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Universal Router Demo</h1>
            <nav>
                <a href="/" class="active">Home</a>
                <a href="/about">About</a>
                <a href="/contact">Contact</a>
                <a href="/blog">Blog</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <h2>Welcome to the Router Demo</h2>
            <p>A clean, portable PHP routing system with friendly URLs</p>
        </div>

        <div class="content-card">
            <h3>What is this?</h3>
            <p>This is a working demonstration of a universal PHP router that transforms ugly URLs into clean, friendly URLs.</p>
            <p>All routing is handled by a simple JSON configuration file - no complex setup required.</p>
        </div>

        <div class="feature-grid">
            <div class="feature">
                <h4>Clean URLs</h4>
                <p>Transform /page.php into /page automatically. No .htaccess required.</p>
            </div>
            <div class="feature">
                <h4>Dynamic Parameters</h4>
                <p>Support for URL parameters like /blog/:slug and /products/:id</p>
            </div>
            <div class="feature">
                <h4>Portable</h4>
                <p>Just copy the router folder to any project and you're ready to go.</p>
            </div>
        </div>

        <div class="content-card">
            <h3>Try It Out</h3>
            <p>Navigate through the menu above or try these example URLs:</p>
            <div class="code-block">
                <code>
                    / (homepage)<br>
                    /about (about page)<br>
                    /contact (contact page)<br>
                    /blog (blog listing)<br>
                    /blog/my-first-post (dynamic blog post)<br>
                    /products/123 (dynamic product page)
                </code>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

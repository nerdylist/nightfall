<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Universal Router Demo</title>
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
            <h2>Blog</h2>
            <p>Example blog posts with dynamic routing</p>
        </div>

        <div class="content-card">
            <h3>Blog Posts</h3>
            <p>Click any post below to see dynamic URL routing in action. Each post uses the pattern /blog/:slug</p>

            <div class="blog-list">
                <div class="blog-item">
                    <a href="/blog/getting-started">
                        <h4>Getting Started with the Router</h4>
                        <p>Learn how to set up the universal router in your PHP project in just a few minutes.</p>
                    </a>
                </div>

                <div class="blog-item">
                    <a href="/blog/dynamic-parameters">
                        <h4>Understanding Dynamic Parameters</h4>
                        <p>How to use :slug and :id in your routes to create dynamic, data-driven pages.</p>
                    </a>
                </div>

                <div class="blog-item">
                    <a href="/blog/clean-urls">
                        <h4>Why Clean URLs Matter</h4>
                        <p>The benefits of using /blog/my-post instead of /blog-post.php?id=5 for SEO and user experience.</p>
                    </a>
                </div>

                <div class="blog-item">
                    <a href="/blog/portable-routing">
                        <h4>Portable Routing Solution</h4>
                        <p>How to move this router between projects with zero configuration changes.</p>
                    </a>
                </div>

                <div class="blog-item">
                    <a href="/blog/no-dependencies">
                        <h4>No Dependencies, Pure PHP</h4>
                        <p>Why this router doesn't require Composer, frameworks, or external libraries.</p>
                    </a>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h3>How It Works</h3>
            <p>The blog listing uses a static route:</p>
            <div class="param-badge">/blog → pages/blog.php</div>
            <p style="margin-top: 1rem;">Individual posts use a dynamic route:</p>
            <div class="param-badge">/blog/:slug → pages/blog-post.php</div>
            <p style="margin-top: 1rem;">The :slug parameter is captured and made available to the destination page via $_ROUTE_PARAMS['slug']</p>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

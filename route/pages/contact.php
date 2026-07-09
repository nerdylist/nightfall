<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - Universal Router Demo</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>Universal Router Demo</h1>
            <nav>
                <a href="/">Home</a>
                <a href="/about">About</a>
                <a href="/contact" class="active">Contact</a>
                <a href="/blog">Blog</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="hero">
            <h2>Contact</h2>
            <p>Get in touch or learn more about the router</p>
        </div>

        <div class="content-card">
            <h3>Questions?</h3>
            <p>This router is designed to be simple and self-explanatory, but if you have questions, check out the CLAUDE.md file in the router/ folder.</p>
            <p>It contains complete documentation on installation, configuration, and advanced usage.</p>
        </div>

        <div class="content-card">
            <h3>Documentation Location</h3>
            <div class="code-block">
                <code>
                    router/CLAUDE.md - Complete setup and usage guide<br>
                    router/Router.php - The router class itself<br>
                    router/routes.json - Route configuration
                </code>
            </div>
        </div>

        <div class="content-card">
            <h3>Current Route</h3>
            <p>You accessed this page via the clean URL:</p>
            <div class="param-badge">/contact</div>
            <p style="margin-top: 1rem;">Which maps to the destination file:</p>
            <div class="param-badge">pages/contact.php</div>
        </div>
    </main>

    <footer>
        <p>&copy; 2026 Universal Router Demo</p>
    </footer>
</body>
</html>

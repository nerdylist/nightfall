# Grave Rising — Web Prototype

Frontend-only UI prototype for graverising.com. No framework, no Composer/npm
dependencies — plain PHP + CSS + JS, SQLite planned but not wired.

## Run locally

```
php -S localhost:8990 -t /Volumes/Crucial/GAMES/livingdead/web
```

Then visit `http://localhost:8990/`.

## Routing

No router. Caddy (or PHP's built-in server) serves this directory's root
directly. `/keeper` works automatically because `keeper/` is a real
subdirectory containing its own `index.php` — `file_server` + `php_fastcgi`
resolve `graverising.com/keeper` to `keeper/index.php` with zero extra config.

## Status

UI prototype only. No real authentication, sessions, or database writes.
`schema.sql` documents the intended SQLite schema but is not executed —
no `.db` file is created. See inline `BACKEND WIRING GOES HERE` comments
in `register.php`, `login.php`, `keeper/index.php`, and their JS files for
where real logic will be added in a future pass.

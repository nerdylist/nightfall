<?php
/**
 * Environment loader / configuration.
 *
 * Reads the project .env file and builds a typed $CONFIG array that is
 * made available in the including scope. Safe to include from any
 * root-level page file. Echoes nothing.
 */

if (!function_exists('forum_load_env')) {
    /**
     * Parse a .env file into an associative array.
     *
     * - Skips blank lines and lines starting with '#'.
     * - Splits on the FIRST '=' only.
     * - Trims whitespace around key and value.
     * - Strips a single wrapping pair of single OR double quotes from the value.
     *
     * @param string $path Absolute path to the .env file.
     * @return array<string,string>
     */
    function forum_load_env($path)
    {
        $env = [];

        if (!is_file($path) || !is_readable($path)) {
            return $env;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return $env;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || $trimmed[0] === '#') {
                continue;
            }

            $pos = strpos($trimmed, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($trimmed, 0, $pos));
            $value = trim(substr($trimmed, $pos + 1));

            if ($key === '') {
                continue;
            }

            // Strip a single matching pair of wrapping quotes.
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            $env[$key] = $value;
        }

        return $env;
    }
}

$forum_env = forum_load_env(__DIR__ . '/.env');

// Friendly-URL routing (router/Router.php) requires destination files from
// inside a class method, so a bare `$CONFIG = [...]` here only lands in that
// method's local scope, not PHP's global scope. bbs/db.php reaches into the
// real global scope via `global $CONFIG;`, so populate $GLOBALS explicitly
// to keep that working regardless of the calling context.
$GLOBALS['CONFIG'] = [
    'SITE_NAME'     => isset($forum_env['SITE_NAME']) && $forum_env['SITE_NAME'] !== ''
        ? (string) $forum_env['SITE_NAME']
        : 'Nexus',
    'DEFAULT_THEME' => isset($forum_env['DEFAULT_THEME']) && $forum_env['DEFAULT_THEME'] !== ''
        ? (string) $forum_env['DEFAULT_THEME']
        : 'midnight',
    'DB_FILE'        => isset($forum_env['DB_FILE']) && $forum_env['DB_FILE'] !== ''
        ? (string) $forum_env['DB_FILE']
        : 'forum.db',
    'ADMIN_EMAIL'    => isset($forum_env['ADMIN_EMAIL']) && $forum_env['ADMIN_EMAIL'] !== ''
        ? (string) $forum_env['ADMIN_EMAIL']
        : 'admin@nexus.test',
    'ADMIN_PASSWORD' => isset($forum_env['ADMIN_PASSWORD']) && $forum_env['ADMIN_PASSWORD'] !== ''
        ? (string) $forum_env['ADMIN_PASSWORD']
        : 'changeme123',
    'ADMIN_USERNAME' => isset($forum_env['ADMIN_USERNAME']) && $forum_env['ADMIN_USERNAME'] !== ''
        ? (string) $forum_env['ADMIN_USERNAME']
        : 'admin',
    'UPLOAD_DIR'         => isset($forum_env['UPLOAD_DIR']) && $forum_env['UPLOAD_DIR'] !== ''
        ? (string) $forum_env['UPLOAD_DIR']
        : 'up',
    'UPLOAD_MAX_BYTES'   => isset($forum_env['UPLOAD_MAX_BYTES']) && $forum_env['UPLOAD_MAX_BYTES'] !== ''
        ? (int) $forum_env['UPLOAD_MAX_BYTES']
        : 5242880,
    'UPLOAD_ALLOWED_EXT' => isset($forum_env['UPLOAD_ALLOWED_EXT']) && $forum_env['UPLOAD_ALLOWED_EXT'] !== ''
        ? (string) $forum_env['UPLOAD_ALLOWED_EXT']
        : 'jpg,jpeg,png,gif,webp',
];
$CONFIG = $GLOBALS['CONFIG'];

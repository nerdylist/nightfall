<?php
/**
 * BBCode -> safe HTML renderer for Nexus Forum.
 *
 * Usage:
 *   require_once __DIR__ . '/lib/bbcode.php';
 *   echo bbcode_to_html($post['body']);   // render a post body to HTML
 *   echo bbcode_excerpt($post['body']);   // plain-text excerpt for listings
 *
 * Functions:
 *   - bbcode_to_html(string $input): string
 *       Converts a limited, safe subset of BBCode to HTML.
 *   - bbcode_excerpt(string $input, int $len = 160): string
 *       Strips all BBCode/HTML and returns a truncated plain-text snippet.
 *   - bb_is_safe_url(string $url): bool  (internal helper)
 *       Whitelist URL validator for [url]/[img] targets.
 *
 * Security notes:
 *   - ALL input is htmlspecialchars()-escaped FIRST (ENT_QUOTES, UTF-8) before any
 *     tag parsing, so any raw HTML/script the user typed is neutralized (XSS). Every
 *     subsequent match runs against the already-escaped string.
 *   - [code] blocks are extracted before any other parsing so their contents are
 *     rendered literally (no nested BBCode), and restored last.
 *   - [url]/[img] targets are validated against a strict whitelist (http(s):// or
 *     /up/ or /bbs/up/ relative paths). Anything else (javascript:, data:, vbscript:, ...) is
 *     rejected and the raw bracket text is emitted instead of a link/image.
 *   - Generated <a>/<img> tags carry rel="nofollow noopener" target="_blank".
 */

if (!function_exists('bb_is_safe_url')) {
    /**
     * Whitelist validator for [url]/[img] targets. Allows only absolute http(s)
     * URLs and /up/ or /bbs/up/ relative paths; rejects javascript:, data:, vbscript:, etc.
     * The incoming value may be htmlspecialchars-escaped (& -> &amp;); scheme
     * prefixes are unaffected by escaping, so prefix checks are safe.
     */
    function bb_is_safe_url(string $url): bool {
        $u = strtolower(trim($url));
        if ($u === '') {
            return false;
        }
        if (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) {
            return true;
        }
        if (str_starts_with($u, '/up/') || str_starts_with($u, '/bbs/up/')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('bbcode_to_html')) {
    /**
     * Convert a safe subset of BBCode into HTML. Input is escaped first, then
     * tags are parsed, URLs auto-linked, and text wrapped into paragraphs.
     */
    function bbcode_to_html(string $input): string {
        // STEP 1: Escape EVERYTHING first. All later matching is against this string.
        $text = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // STEP 2: Pull out [code]...[/code] blocks before any other parsing so their
        // contents are rendered literally (no nested BBCode). Store rendered HTML
        // against a collision-resistant placeholder; restore at the very end.
        $codeTokens = [];
        $text = preg_replace_callback(
            '/\[code\](.*?)\[\/code\]/is',
            function ($m) use (&$codeTokens) {
                $token = "\x01CODE-" . uniqid('', true) . "-" . count($codeTokens) . "\x01";
                // $m[1] is already htmlspecialchars-escaped; keep it literal.
                $codeTokens[$token] = '<pre class="bb-code"><code>' . $m[1] . '</code></pre>';
                return $token;
            },
            $text
        );

        // STEP 3: Inline / block tags (case-insensitive tag names).
        // Loop the inline replacements until stable so nested tags resolve.
        $inlinePatterns = [
            '/\[b\](.*?)\[\/b\]/is'         => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/is'         => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/is'         => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/is'         => '<s>$1</s>',
            '/\[quote\](.*?)\[\/quote\]/is' => '<blockquote class="bb-quote">$1</blockquote>',
        ];
        do {
            $before = $text;
            $text = preg_replace(array_keys($inlinePatterns), array_values($inlinePatterns), $text);
        } while ($text !== $before);

        // [url=URL]Anchor[/url] — validate URL, else emit the raw (escaped) bracket text.
        $text = preg_replace_callback(
            '/\[url=([^\]]+)\](.*?)\[\/url\]/is',
            function ($m) {
                if (!bb_is_safe_url($m[1])) {
                    return $m[0];
                }
                return '<a href="' . $m[1] . '" rel="nofollow noopener" target="_blank">' . $m[2] . '</a>';
            },
            $text
        );

        // [url]URL[/url]
        $text = preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/is',
            function ($m) {
                if (!bb_is_safe_url($m[1])) {
                    return $m[0];
                }
                return '<a href="' . $m[1] . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>';
            },
            $text
        );

        // [img]URL[/img]
        $text = preg_replace_callback(
            '/\[img\](.*?)\[\/img\]/is',
            function ($m) {
                if (!bb_is_safe_url($m[1])) {
                    return $m[0];
                }
                return '<img src="' . $m[1] . '" alt="" class="bb-img" loading="lazy">';
            },
            $text
        );

        // STEP 5: Auto-link bare http(s) URLs that are NOT already inside an
        // <a>...</a> or an <img> tag. To avoid re-linking those, swap the
        // already-generated <a>...</a> and <img> tags out for placeholder tokens
        // first, run the bare-URL regex over what's left, then restore the tokens.
        $linkTokens = [];
        $protect = function ($pattern) use (&$text, &$linkTokens) {
            $text = preg_replace_callback(
                $pattern,
                function ($m) use (&$linkTokens) {
                    $token = "\x02LINK-" . uniqid('', true) . "-" . count($linkTokens) . "\x02";
                    $linkTokens[$token] = $m[0];
                    return $token;
                },
                $text
            );
        };
        $protect('/<a\b[^>]*>.*?<\/a>/is');
        $protect('/<img\b[^>]*>/is');

        $text = preg_replace_callback(
            '#\bhttps?://[^\s<]+#i',
            function ($m) {
                $url = $m[0];
                $trail = '';
                // Trim trailing punctuation off the matched URL.
                while ($url !== '' && strpbrk(substr($url, -1), '.,!?);:') !== false) {
                    $trail = substr($url, -1) . $trail;
                    $url = substr($url, 0, -1);
                }
                if ($url === '') {
                    return $m[0];
                }
                return '<a href="' . $url . '" rel="nofollow noopener" target="_blank">' . $url . '</a>' . $trail;
            },
            $text
        );

        // Restore protected <a>/<img> tokens.
        if ($linkTokens) {
            $text = strtr($text, $linkTokens);
        }

        // STEP 6: Paragraphs. Split on blank lines; wrap plain text paragraphs in
        // <p> with single \n -> <br>. Block-level paragraphs (a blockquote, or a
        // code placeholder token) are emitted as-is without <p> or <br>.
        // Mirrors the paragraph handling in thread.php (~78-102).
        $paragraphs = preg_split('/\n\n+/', trim($text));
        $out = [];
        foreach ($paragraphs as $paragraph) {
            if (trim($paragraph) === '') {
                continue;
            }
            $trimmed = trim($paragraph);
            $isBlock =
                str_starts_with($trimmed, '<blockquote') ||
                (str_contains($trimmed, "\x01CODE-") && preg_match('/^\x01CODE-[^\x01]+\x01$/', $trimmed) === 1);
            if ($isBlock) {
                $out[] = $trimmed;
            } else {
                $out[] = '<p>' . nl2br($paragraph, false) . '</p>';
            }
        }
        $text = implode("\n", $out);

        // Restore [code] blocks LAST (after auto-linking and paragraph wrapping).
        if ($codeTokens) {
            $text = strtr($text, $codeTokens);
        }

        return $text;
    }
}

if (!function_exists('bbcode_excerpt')) {
    /**
     * Strip all BBCode and HTML, decode entities, collapse whitespace, and
     * truncate to $len characters (appending an ellipsis only if truncated).
     */
    function bbcode_excerpt(string $input, int $len = 160): string {
        // Strip BBCode tags (both [tag] and [tag=value] / [/tag]).
        $text = preg_replace('/\[\/?[a-z0-9]+(=[^\]]*)?\]/i', '', $input);
        // Strip any HTML tags.
        $text = strip_tags((string) $text);
        // Decode entities so e.g. &amp; becomes &.
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Collapse all whitespace to single spaces and trim.
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text, 'UTF-8') > $len) {
            return mb_substr($text, 0, $len, 'UTF-8') . "\u{2026}";
        }
        return $text;
    }
}

<?php
require __DIR__ . '/../lib/bbcode.php';

$pass = 0;
$fail = 0;

function check(string $name, bool $cond): void {
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS: {$name}\n";
    } else {
        $fail++;
        echo "FAIL: {$name}\n";
    }
}

// XSS: raw HTML must be neutralized.
$xss = bbcode_to_html('<script>alert(1)</script>');
check('XSS escapes <script>', !str_contains($xss, '<script>') && str_contains($xss, '&lt;script&gt;'));

// Basic inline tags.
check('[b] -> <strong>', str_contains(bbcode_to_html('[b]hi[/b]'), '<strong>hi</strong>'));
check('[i] -> <em>',     str_contains(bbcode_to_html('[i]hi[/i]'), '<em>hi</em>'));
check('[u] -> <u>',      str_contains(bbcode_to_html('[u]hi[/u]'), '<u>hi</u>'));
check('[s] -> <s>',      str_contains(bbcode_to_html('[s]hi[/s]'), '<s>hi</s>'));

// Quote.
check('[quote] -> blockquote', str_contains(bbcode_to_html('[quote]hi[/quote]'), '<blockquote class="bb-quote">hi</blockquote>'));

// URLs.
$urlAnchor = bbcode_to_html('[url=https://example.com]Anchor[/url]');
check('[url=...]Anchor[/url]', str_contains($urlAnchor, '<a href="https://example.com" rel="nofollow noopener" target="_blank">Anchor</a>'));

$urlPlain = bbcode_to_html('[url]https://example.com[/url]');
check('[url]URL[/url] anchor with URL text', str_contains($urlPlain, '<a href="https://example.com" rel="nofollow noopener" target="_blank">https://example.com</a>'));

// Bare auto-link.
$auto = bbcode_to_html('visit https://example.com today');
check('bare URL auto-linked', str_contains($auto, '<a href="https://example.com" rel="nofollow noopener" target="_blank">https://example.com</a>'));

// Image with /up/ path.
$img = bbcode_to_html('[img]/up/abc.jpg[/img]');
check('[img] -> <img>', str_contains($img, '<img src="/up/abc.jpg" alt="" class="bb-img" loading="lazy">'));

// javascript: rejection.
$js = bbcode_to_html('[url=javascript:alert(1)]x[/url]');
$jsLower = strtolower($js);
check('javascript: rejected (no js in href/src)',
    !str_contains($jsLower, 'href="javascript:') &&
    !str_contains($jsLower, "href='javascript:") &&
    !str_contains($jsLower, 'src="javascript:') &&
    !str_contains($jsLower, "src='javascript:") &&
    !str_contains($jsLower, '<a '));

// Nested code: no parsing inside.
$code = bbcode_to_html('[code][b]hi[/b][/code]');
check('code block escapes [b] literally', str_contains($code, '[b]hi[/b]') && !str_contains($code, '<strong>'));

// Excerpt.
$long = '[b]Hello[/b] [url=https://example.com]world[/url] this is a fairly long body of text that should be truncated nicely because it definitely exceeds the requested length boundary for the excerpt output here.';
$ex = bbcode_excerpt($long, 50);
check('excerpt has no HTML tags', !preg_match('/<[a-z]/i', $ex));
check('excerpt has no [ bbcode brackets', !str_contains($ex, '['));
check('excerpt length <= 51 (50 + ellipsis)', mb_strlen($ex, 'UTF-8') <= 51);
check('excerpt ends with ellipsis when truncated', str_ends_with($ex, "\u{2026}"));

$short = bbcode_excerpt('[b]short[/b]', 160);
check('short excerpt not truncated (no ellipsis)', $short === 'short');

echo "\n----------------------------------------\n";
echo "TOTAL: {$pass} passed, {$fail} failed\n";

exit($fail === 0 ? 0 : 1);

<?php
/**
 * Shared category badge helpers — the SINGLE source of badge logic.
 *
 * A category "badge" is now stored in the (legacy-named) `icon` column, which
 * may hold a short text/emoji value OR an image URL. `color` holds a hex string.
 *
 * forum_category_color(array $category): string
 *   Returns a SERVER-VALIDATED hex color. Only validated hex may ever reach a
 *   style attribute (this is the injection guard for the --cat-color handoff).
 *
 * forum_category_badge(array $category): string
 *   Returns the INNER HTML for the badge: an <img> for safe image URLs, an
 *   escaped emoji/text value, or a single-letter fallback from the name.
 */

require_once __DIR__ . '/../lib/bbcode.php';

if (!function_exists('forum_category_color')) {
    function forum_category_color(array $category): string
    {
        $color = (string)($category['color'] ?? '');
        // Injection guard: only a clean 3- or 6-digit hex may reach a style attr.
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color)) {
            return $color;
        }
        return '#7a64f5'; // theme accent default
    }
}

if (!function_exists('forum_category_badge_is_image')) {
    /**
     * True when the category badge resolves to an image (safe URL), so callers
     * can drop the chip background/border and show the image on its own.
     */
    function forum_category_badge_is_image(array $category): bool
    {
        $val = trim((string)($category['icon'] ?? ''));
        return $val !== '' && bb_is_safe_url($val);
    }
}

if (!function_exists('forum_category_badge')) {
    function forum_category_badge(array $category): string
    {
        // Column stays named `icon` but now holds a badge value (text/emoji/URL).
        $val = trim((string)($category['icon'] ?? ''));

        if ($val !== '' && bb_is_safe_url($val)) {
            return '<img src="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" alt="" class="cat-badge-img" loading="lazy">';
        }

        if ($val !== '') {
            return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
        }

        // Fallback: first letter of the category name, uppercased + escaped.
        return htmlspecialchars(
            mb_strtoupper(mb_substr((string)($category['name'] ?? '?'), 0, 1)),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

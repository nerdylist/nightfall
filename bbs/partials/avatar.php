<?php
/**
 * Avatar partial.
 *
 * Usage:
 *   require_once __DIR__ . '/avatar.php';   // include once
 *   echo render_avatar('Devon Marsh', 40);  // or just render_avatar(...) to echo
 *
 * render_avatar($name, $size = 40) prints a circular avatar with deterministic
 * background color and the user's initials.
 */

if (!function_exists('forum_avatar_initials')) {
    function forum_avatar_initials($name)
    {
        $name = (string) $name;
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) === 0) {
            $initials = '?';
        } elseif (count($words) === 1) {
            $w = $words[0];
            $initials = (mb_strlen($w) >= 2) ? mb_substr($w, 0, 2) : mb_substr($w, 0, 1);
        } else {
            $initials = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
        }
        return mb_strtoupper($initials);
    }
}

if (!function_exists('render_avatar')) {
    function render_avatar($name, $size = 40)
    {
        $name = (string) $name;

        // Compute initials.
        $initials = forum_avatar_initials($name);

        // Deterministic background color.
        $hash = crc32($name);
        $hue  = abs($hash) % 360;
        $bg   = "hsl({$hue}, 55%, 52%)";

        // Map the requested pixel size to the nearest named size class.
        // Sizes: sm=24, md=28, lg=36, xl=40, 2xl=72, 3xl=120.
        $size  = (int) $size;
        $sizes = [24 => 'sm', 28 => 'md', 36 => 'lg', 40 => 'xl', 72 => '2xl', 120 => '3xl'];
        $best  = 'xl';
        $bestDiff = PHP_INT_MAX;
        foreach ($sizes as $px => $name2) {
            $diff = abs($px - $size);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $name2;
            }
        }

        echo '<div class="avatar avatar--' . $best . '"'
           . ' style="--avatar-bg: ' . htmlspecialchars($bg) . '">'
           . htmlspecialchars($initials)
           . '</div>';
    }
}

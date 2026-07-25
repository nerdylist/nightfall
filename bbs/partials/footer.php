<?php
/**
 * ONE FOOTER ACROSS THE WHOLE SITE (Boss 2026-07-25): the forum renders the
 * exact same partials/footer.php as every host page. The forum's script
 * bundle rides the host footer's existing $pageJs hook. The old
 * "Prototype - mock data" footer is retired.
 */
require_once __DIR__ . '/../../config.php'; // asset_url() for the host footer

$pageJs = array_merge($pageJs ?? [], [
    '/bbs/js/theme.js',
    '/bbs/js/general.js',
    '/bbs/js/chat.js',
    '/bbs/js/modal.js',
    '/bbs/js/post-actions.js',
    '/bbs/js/editor.js',
]);

include __DIR__ . '/../../partials/footer.php';

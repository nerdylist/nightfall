<?php
if (!isset($CONFIG)) { require __DIR__ . '/../config.php'; }
?>
<footer class="site-footer"><span><?= htmlspecialchars($CONFIG['SITE_NAME']) ?></span> <span>Prototype - mock data</span></footer>
<script src="/bbs/js/theme.js"></script>
<script src="/bbs/js/general.js"></script>
<script src="/js/nav-menu.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/nav-menu.js') ?>"></script>
<script src="/js/nav-search.js?v=<?= (int) @filemtime(__DIR__ . '/../../js/nav-search.js') ?>"></script>
<script src="/bbs/js/chat.js"></script>
<script src="/bbs/js/modal.js"></script>
<script src="/bbs/js/post-actions.js"></script>
<script src="/bbs/js/editor.js"></script>
</body>
</html>

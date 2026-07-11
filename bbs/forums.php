<?php
// The forums listing now lives at the forum root (/bbs/). Permanently
// redirect the old /bbs/forums URL (and direct /bbs/forums.php hits) so
// existing links and bookmarks keep working with one canonical URL.
header('Location: /bbs/', true, 301);
exit;

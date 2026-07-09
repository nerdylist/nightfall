<?php
if (!isset($CONFIG)) { require __DIR__ . '/../config.php'; }
$BASE = $BASE ?? '';
$EXTRA_CSS = $EXTRA_CSS ?? [];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($CONFIG['SITE_NAME']) ?></title>
<link rel="stylesheet" href="<?= $BASE ?>css/themes.css">
<link rel="stylesheet" href="<?= $BASE ?>css/general.css">
<link rel="stylesheet" href="/css/site-nav.css?v=<?= (int) @filemtime(__DIR__ . '/../../css/site-nav.css') ?>">
<link rel="stylesheet" href="<?= $BASE ?>css/forum.css">
<link rel="stylesheet" href="<?= $BASE ?>css/avatar.css">
<link rel="stylesheet" href="<?= $BASE ?>css/thread.css">
<link rel="stylesheet" href="<?= $BASE ?>css/chat.css">
<link rel="stylesheet" href="<?= $BASE ?>css/modal.css">
<link rel="stylesheet" href="<?= $BASE ?>css/profile.css">
<link rel="stylesheet" href="<?= $BASE ?>css/auth.css">
<link rel="stylesheet" href="<?= $BASE ?>css/settings.css">
<link rel="stylesheet" href="<?= $BASE ?>css/editor.css">
<link rel="stylesheet" href="<?= $BASE ?>css/write.css">
<link rel="stylesheet" href="<?= $BASE ?>css/forums.css">
<script>(function(){document.documentElement.dataset.theme=<?= json_encode($CONFIG['DEFAULT_THEME']) ?>;})();</script>
<?php foreach ((array)$EXTRA_CSS as $href) { echo '<link rel="stylesheet" href="' . htmlspecialchars($BASE . $href) . '">' . "\n"; } ?>
</head>
<body>

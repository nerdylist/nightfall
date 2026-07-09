<?php
/**
 * Shared public-site header partial.
 * Expects optional $pageTitle and $pageCss (array of extra css files) to be set before include.
 */
$pageTitle = $pageTitle ?? 'The Dead Last';
$pageCss = $pageCss ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/css/base.css">
  <?php foreach ($pageCss as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
  <?php endforeach; ?>
</head>
<body>
<?php include __DIR__ . '/nav.php'; ?>

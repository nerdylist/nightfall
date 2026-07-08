<?php
/**
 * Shared Keeper (admin) header partial. Separate from the public site header.
 * Expects optional $pageTitle and $pageCss.
 */
$pageTitle = $pageTitle ?? 'Keeper — Grave Rising Admin';
$pageCss = $pageCss ?? [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="/css/base.css">
  <link rel="stylesheet" href="/css/keeper.css">
  <?php foreach ($pageCss as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
  <?php endforeach; ?>
</head>
<body class="keeper">
<header class="keeper-header">
  <div class="container keeper-header__inner">
    <a href="/keeper/index.php" class="keeper-header__brand">KEEPER</a>
    <nav class="keeper-header__nav">
      <a href="/keeper/dashboard.php" class="keeper-header__link">Dashboard</a>
      <a href="/keeper/meshy.php" class="keeper-header__link">Meshy</a>
      <a href="/index.php" class="keeper-header__link text-muted">Back to site</a>
    </nav>
  </div>
</header>

<footer class="site-footer">
  <div class="container site-footer__inner">
    <span class="text-muted">&copy; <?= date('Y') ?> Living Dead Studios. THE DEAD LAST.</span>
    <span class="text-muted">Survival is only half of the game.</span>
  </div>
</footer>
<?php $pageJs = $pageJs ?? []; ?>
<script src="/js/base.js"></script>
<?php foreach ($pageJs as $js): ?>
<script src="<?= htmlspecialchars($js) ?>"></script>
<?php endforeach; ?>
</body>
</html>

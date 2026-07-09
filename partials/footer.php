<footer class="site-footer">
  <div class="container site-footer__inner">
    <a href="/index.php" class="site-footer__brand">THE DEAD LAST</a>
    <div class="site-footer__links">
      <a href="#" class="site-footer__link">Privacy Policy</a>
      <a href="#" class="site-footer__link">Terms of Service</a>
      <a href="#" class="site-footer__link">Support</a>
    </div>
    <span class="text-muted site-footer__copy">&copy; <?= date('Y') ?> Living Dead Studios. All rights reserved.</span>
  </div>
</footer>
<?php $pageJs = $pageJs ?? []; ?>
<script src="<?= htmlspecialchars(asset_url('/js/base.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_url('/js/nav-search.js')) ?>"></script>
<?php foreach ($pageJs as $js): ?>
<script src="<?= htmlspecialchars(asset_url($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>

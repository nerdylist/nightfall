<footer class="keeper-footer">
  <div class="container">
    <span class="text-muted">Keeper admin &mdash; The Dead Last prototype</span>
  </div>
</footer>
<?php $pageJs = $pageJs ?? []; ?>
<script src="<?= htmlspecialchars(asset_url('/js/base.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_url('/js/keeper.js')) ?>"></script>
<?php foreach ($pageJs as $js): ?>
<script src="<?= htmlspecialchars(asset_url($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>

    <footer class="keeper-footer">
      <span class="text-muted">Keeper — The Dead Last</span>
    </footer>
  </div><!-- /.keeper-content -->

  <!-- Right sidebar (rail): full-height, persistent across all Keeper pages,
       mirrors the left nav. A page may set $keeperRail (HTML string) to fill
       it; otherwise a default panel renders. -->
  <aside class="keeper-rail" id="keeper-rail">
    <?php if (!empty($keeperRail)): ?>
      <?= $keeperRail ?>
    <?php else: ?>
      <div class="keeper-rail__panel">
        <h2 class="keeper-rail__title">Panel</h2>
        <p class="keeper-rail__empty text-muted">Right sidebar — ready for content.</p>
      </div>
    <?php endif; ?>
  </aside>
</div><!-- /.keeper-shell -->
<?php $pageJs = $pageJs ?? []; ?>
<script src="<?= htmlspecialchars(asset_url('/js/base.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_url('/js/keeper.js')) ?>"></script>
<?php foreach ($pageJs as $js): ?>
<script src="<?= htmlspecialchars(asset_url($js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>

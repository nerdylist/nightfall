<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Shop — The Dead Last';
$pageCss = ['/css/shop.css'];
include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="<?= htmlspecialchars(asset_url('/css/model-embed.css')) ?>">
<script type="importmap">
{
	"imports": {
		"three": "/js/vendor/three.module.js"
	}
}
</script>

<main>
  <section class="shop-hero">
    <div class="container shop-hero__grid">
      <div class="shop-hero__copy">
        <h1 class="shop-hero__title">Shop</h1>
        <p class="text-muted shop-hero__subtext">The store is being stocked. Check back soon.</p>
        <a href="/" class="btn btn-ghost">Back to Home</a>
      </div>
      <div id="shop-model" class="shop-model" aria-label="The Dead Last shopper" role="img"></div>
    </div>
  </section>
</main>

<script type="module" src="<?= htmlspecialchars(asset_url('/js/shop-model.js')) ?>"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>

<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Grave Rising — Survival is only half of the game.';
$pageCss = ['/css/index.css', '/css/home-hero.css'];
include __DIR__ . '/partials/header.php';
?>
<link rel="stylesheet" href="/css/model-embed.css">
<script type="importmap">
{
	"imports": {
		"three": "/js/vendor/three.module.js"
	}
}
</script>

<main>
  <section class="hero">
    <div class="container hero__grid">
      <div class="hero__inner">
        <p class="hero__eyebrow">Living Dead Studios</p>
        <h1 class="hero__title">
          <span class="hero__title-line">GRAVE</span>
          <span class="hero__title-line">RISING</span>
        </h1>
        <p class="hero__tagline">Survival is only half of the game.</p>
        <div class="hero__actions">
          <a href="/register.php" class="btn btn-primary">Create Survivor</a>
          <a href="/login.php" class="btn btn-ghost">Login</a>
        </div>
      </div>
      <div class="hero__model">
        <div id="home-hero-model" class="hero__model-container"></div>
      </div>
    </div>
  </section>

  <section class="pitch">
    <div class="container pitch__grid">
      <div class="card pitch__card">
        <h2 class="pitch__heading">Survival Royale</h2>
        <p class="text-muted">
          A persistent, open 1950s world where every survivor scavenges,
          builds, and fights to stay alive &mdash; against the infected,
          and sometimes against each other.
        </p>
      </div>
      <div class="card pitch__card">
        <h2 class="pitch__heading">Permadeath</h2>
        <p class="text-muted">
          Every decision matters. Death is final &mdash; inventory, skills,
          and progress are gone, and a new survivor begins the story again.
        </p>
      </div>
      <div class="card pitch__card">
        <h2 class="pitch__heading">Human vs. Zombie</h2>
        <p class="text-muted">
          Getting bitten isn't the end &mdash; it's a new beginning. Turn
          into the infected and play an entirely different game, with a
          dangerous path back to humanity for those who dare.
        </p>
      </div>
    </div>
  </section>
</main>

<script type="module" src="/js/home-hero-model.js?v=1"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>

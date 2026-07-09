<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'The Dead Last — Survival is only half of the game.';
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
          <img src="/assets/logo.png" alt="THE DEAD LAST" width="960" height="870" class="hero__logo">
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

  <section class="sides">
    <div class="container">
      <h2 class="sides__heading">Choose Your Side</h2>
      <div class="sides__grid">
        <div class="card sides__panel sides__panel--human">
          <h3 class="sides__panel-heading">Humans</h3>
          <p class="text-muted">
            Scavenge what's left. Build caches no one else can find.
          </p>
          <p class="text-muted">
            Every stranger is a risk &mdash; trust them, and you might
            not live to regret it.
          </p>
          <p class="text-muted">
            Stay alive. Stay quiet. Stay armed.
          </p>
        </div>
        <div class="card sides__panel sides__panel--zombie">
          <h3 class="sides__panel-heading">Zombies</h3>
          <p class="text-muted">
            Getting bitten isn't the end &mdash; it's a new beginning.
          </p>
          <p class="text-muted">
            Feed to grow stronger. Hunt with enhanced smell and hearing.
          </p>
          <p class="text-muted">
            Evolve through mutation, from Fresh to something far worse.
          </p>
        </div>
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
        <h2 class="pitch__heading">True Death</h2>
        <p class="text-muted">
          Permadeath is real and final. Die, and your inventory, skills,
          and XP go with you &mdash; character deleted, no respawns. A new
          survivor starts the story from nothing.
        </p>
      </div>
      <div class="card pitch__card">
        <h2 class="pitch__heading">Redemption</h2>
        <p class="text-muted">
          A zombie that refuses to feed can claw its way back to
          humanity &mdash; a dangerous path back, not a free pass.
        </p>
      </div>
    </div>
  </section>

  <section class="cta-strip">
    <div class="container cta-strip__inner">
      <p class="cta-strip__line">The line between human and monster is one bite.</p>
      <div class="cta-strip__actions">
        <a href="/register.php" class="btn btn-primary">Create Survivor</a>
        <a href="/login.php" class="btn btn-ghost">Login</a>
      </div>
    </div>
  </section>
</main>

<script type="module" src="/js/home-hero-model.js?v=1"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>

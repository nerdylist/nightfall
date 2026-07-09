<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'The Dead Last — Survival is only half of the game.';
$pageCss = ['/css/index.css'];
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
  <section class="hero">
    <div class="hero__bg">
      <img src="/assets/images/1.png" alt="" class="hero__bg-img">
    </div>
    <div class="container hero__inner">
      <h1 class="hero__title">
        <img src="/assets/logo.png" alt="THE DEAD LAST" width="960" height="870" class="hero__logo">
      </h1>
      <p class="hero__tagline">Survival is only half the game.</p>
      <div class="hero__actions">
        <a href="/register" class="btn btn-primary">Create Survivor</a>
        <a href="#" class="btn btn-ghost btn-trailer"><span class="btn-trailer__glyph">&#9658;</span> Watch Trailer</a>
      </div>
    </div>
    <div class="hero__chevron" aria-hidden="true">&#8964;</div>
  </section>

  <section class="features">
    <div class="container features__grid">
      <div class="feature">
        <img src="/assets/images/2.png" alt="" class="feature__icon">
        <h3 class="feature__heading">Scavenge &amp; Craft</h3>
        <p class="text-muted feature__desc">Search, loot, and craft weapons, tools, and supplies to stay alive.</p>
      </div>
      <div class="feature">
        <img src="/assets/images/3.png" alt="" class="feature__icon">
        <h3 class="feature__heading">Trust No One</h3>
        <p class="text-muted feature__desc">Team up with others, or betray them. Your choices define your story.</p>
      </div>
      <div class="feature">
        <img src="/assets/images/4.png" alt="" class="feature__icon">
        <h3 class="feature__heading">Zombie Threat</h3>
        <p class="text-muted feature__desc">Relentless. Unforgiving. The dead don't stop coming.</p>
      </div>
      <div class="feature">
        <img src="/assets/images/5.png" alt="" class="feature__icon">
        <h3 class="feature__heading">Explore &amp; Endure</h3>
        <p class="text-muted feature__desc">A vast, dynamic world filled with danger, mysteries, and rewards.</p>
      </div>
    </div>
  </section>

  <section class="town">
    <div class="container card town__card">
      <div class="town__copy">
        <h2 class="town__heading">1950s. SMALL TOWN.<br>NO ESCAPE.</h2>
        <p class="text-muted">Civilization has fallen. The dead roam the streets, and the few who remain are fighting for their last chance at survival.</p>
        <a href="#" class="btn btn-ghost">Learn More</a>
      </div>
      <div class="town__carousel" id="town-carousel">
        <div class="town-carousel__track">
          <div class="town-carousel__slide is-active">
            <img src="/assets/images/6.png" alt="Town slide 1">
          </div>
          <div class="town-carousel__slide">
            <img src="/assets/images/7.png" alt="Town slide 2">
          </div>
          <div class="town-carousel__slide">
            <img src="/assets/images/8.png" alt="Town slide 3">
          </div>
        </div>
        <div class="town-carousel__dots">
          <button type="button" class="town-carousel__dot is-active" data-slide="0" aria-label="Slide 1"></button>
          <button type="button" class="town-carousel__dot" data-slide="1" aria-label="Slide 2"></button>
          <button type="button" class="town-carousel__dot" data-slide="2" aria-label="Slide 3"></button>
        </div>
      </div>
    </div>
  </section>

  <section class="community">
    <div class="container card community__card">
      <div class="community__col community__col--social">
        <h2 class="community__heading">Join the Community</h2>
        <p class="text-muted">Share your story. Find allies. Stay updated.</p>
        <div class="community__socials">
          <a href="#" class="community__social" aria-label="Discord"><img src="/assets/images/13.png" alt=""></a>
          <a href="#" class="community__social" aria-label="Twitter"><img src="/assets/images/14.png" alt=""></a>
          <a href="#" class="community__social" aria-label="YouTube"><img src="/assets/images/15.png" alt=""></a>
          <a href="#" class="community__social" aria-label="Reddit"><img src="/assets/images/16.png" alt=""></a>
        </div>
        <img src="/assets/images/12.png" alt="Community photo collage" class="community__collage">
      </div>
      <div class="community__col community__col--model">
        <div id="home-hero-model" class="community__model"></div>
      </div>
      <div class="community__col community__col--news">
        <h2 class="community__heading">Latest News</h2>
        <div class="news-list">
          <a href="#" class="news-item">
            <img src="/assets/images/9.png" alt="" class="news-item__thumb">
            <div class="news-item__body">
              <h3 class="news-item__title">Server Stress Test &mdash; May 25</h3>
              <p class="news-item__date">May 18, 2025</p>
              <p class="news-item__blurb text-muted">Sign up now and help us stress test the servers before launch.</p>
            </div>
          </a>
          <a href="#" class="news-item">
            <img src="/assets/images/10.png" alt="" class="news-item__thumb">
            <div class="news-item__body">
              <h3 class="news-item__title">Developer Update #7</h3>
              <p class="news-item__date">May 12, 2025</p>
              <p class="news-item__blurb text-muted">New AI behaviors, weapon balance, and more.</p>
            </div>
          </a>
          <a href="#" class="news-item">
            <img src="/assets/images/11.png" alt="" class="news-item__thumb">
            <div class="news-item__body">
              <h3 class="news-item__title">New Map Teaser</h3>
              <p class="news-item__date">May 5, 2025</p>
              <p class="news-item__blurb text-muted">Take a look at our newest location coming soon.</p>
            </div>
          </a>
        </div>
        <a href="#" class="btn btn-ghost">View All News</a>
      </div>
    </div>
  </section>
</main>

<script type="module" src="<?= htmlspecialchars(asset_url('/js/home-hero-model.js')) ?>"></script>
<script src="<?= htmlspecialchars(asset_url('/js/home-carousel.js')) ?>"></script>
<?php include __DIR__ . '/partials/footer.php'; ?>

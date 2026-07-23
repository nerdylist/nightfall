<?php
/**
 * THE DEAD LAST — GAME page: the concept pitch.
 *
 * Static marketing/explainer for the top-nav GAME link: what Survival Royale
 * is, the season clock, True Death, the Turn, Redemption, the extraction
 * economy, and the Season Boards. Copy voice: 1950s B&W horror matinee.
 */

require_once __DIR__ . '/config.php';

$pageTitle = 'The Game — The Dead Last';
$pageCss   = ['/css/game.css'];
include __DIR__ . '/partials/header.php';
?>
<main class="game">

  <!-- HERO -->
  <section class="game-hero">
    <div class="container game-hero__inner">
      <p class="game-hero__kicker">A NEW KIND OF NIGHTMARE</p>
      <h1 class="game-hero__title">SURVIVAL&nbsp;ROYALE</h1>
      <p class="game-hero__tag">&ldquo;Survival is only half of the game.&rdquo;</p>
      <p class="game-hero__lead">
        1958. Something went wrong in the little town of Ravenwood, and the
        dead won't stay put. <strong>The Dead Last</strong> is an open-world
        survival game shot in black and white — a persistent town, a ticking
        season, and one rule the marquee never mentions: when you die for
        real, you're gone.
      </p>
      <div class="game-hero__cta">
        <a class="btn btn-primary" href="/register">Join the Season</a>
        <a class="btn btn-ghost" href="/leaderboard">See Who's Winning</a>
      </div>
    </div>
  </section>

  <!-- THE CLOCK -->
  <section class="game-section">
    <div class="container game-section__grid">
      <div class="game-section__text">
        <h2 class="game-section__title">The Clock Is the Score</h2>
        <p>
          Every season runs on a real countdown. Your survivor's score is
          <strong>time survived</strong> — and the clock only ticks while
          you're actually out there. Hide at home, stand at the bank counter,
          idle at a menu: the clock stops. The leaderboard doesn't reward the
          cautious. It rewards the <em>present</em>.
        </p>
        <p>
          When the season ends, the books close. The names at the top stay
          there forever.
        </p>
      </div>
      <div class="game-section__panel">
        <h3 class="game-panel__head">HOW YOU CLIMB</h3>
        <ul class="game-list">
          <li>Spawn into Ravenwood with nothing.</li>
          <li>Scavenge chests, fight what finds you.</li>
          <li>Bank your cash before something takes it.</li>
          <li>Stay alive. Stay <em>in it</em>. The clock is watching.</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- TRUE DEATH -->
  <section class="game-section game-section--dark">
    <div class="container game-section__grid">
      <div class="game-section__panel">
        <h3 class="game-panel__head">WHEN YOU DIE</h3>
        <ul class="game-list">
          <li>Everything you carried drops where you fell.</li>
          <li>Your survivor is erased from your roster. No revives. No second take.</li>
          <li>Their name stays on the season's boards — a legend, or a warning.</li>
          <li>You walk back in fresh: empty hands, day one.</li>
        </ul>
      </div>
      <div class="game-section__text">
        <h2 class="game-section__title">True Death</h2>
        <p>
          Death here isn't a respawn timer. It's an obituary. Weeks of
          progress, a backpack full of gear, an unbanked fortune — one bad
          night in the fog and it all belongs to whoever finds the bag.
        </p>
        <p>
          That's the wager every time you step outside: everything you're
          carrying against everything you might find.
        </p>
      </div>
    </div>
  </section>

  <!-- THE TURN -->
  <section class="game-section">
    <div class="container game-section__grid">
      <div class="game-section__text">
        <h2 class="game-section__title">The Turn</h2>
        <p>
          A bite isn't the end — it's an audition. Get infected, and instead
          of a game-over screen you get a <strong>whole different game</strong>:
          you rise as the zombie. Your gear, your skills, your humanity —
          gone. What's left is hunger.
        </p>
        <p>
          Feed and you grow strong. Starve and you wither toward the crawl.
          Hunt with the horde, or hunt the horde. Your choice — what's left
          of you.
        </p>
      </div>
      <div class="game-section__panel">
        <h3 class="game-panel__head">REDEMPTION</h3>
        <p class="game-panel__note">
          There is one way back. Don't eat a soul. Don't kill the living.
          Put down your own kind, hide from everyone, and let the rage bleed
          out of you — while your body gets weaker and louder by the hour.
          Survive that gauntlet, and you wake up human, with everything you
          were. Almost nobody makes it. That's the point.
        </p>
      </div>
    </div>
  </section>

  <!-- ECONOMY -->
  <section class="game-section game-section--dark">
    <div class="container game-section__grid">
      <div class="game-section__panel">
        <h3 class="game-panel__head">THREE PLACES FOR YOUR MONEY</h3>
        <ul class="game-list">
          <li><strong>Your pockets</strong> — spends anywhere, dies with you.</li>
          <li><strong>The bank</strong> — walk it to the teller in town; yours forever.</li>
          <li><strong>The vault</strong> — your account's deep storage for the things that matter.</li>
        </ul>
      </div>
      <div class="game-section__text">
        <h2 class="game-section__title">Get Rich or Die in Ravenwood</h2>
        <p>
          Loot is an extraction game. Cash on your body is <em>loud</em> —
          every step away from the bank is a bet that you'll live to deposit
          it. Die on the way, and your fortune sits in a bag on the street
          with a season full of strangers walking past.
        </p>
        <p>
          Survivors are temporary. The account — your bank, your vault, your
          legend — survives them all.
        </p>
      </div>
    </div>
  </section>

  <!-- SEASON BOARDS -->
  <section class="game-section">
    <div class="container">
      <h2 class="game-section__title game-section__title--center">Twenty Ways to Be Remembered</h2>
      <p class="game-boards__lead">
        The season keeps books on everything: the maniacs, the hunters, the
        packrats, the ghosts who never raised a hand, the insomniacs who never
        touched pause, and the poor souls in the Darwin column. Every life you
        run writes its own line.
      </p>
      <div class="game-boards__icons">
        <img src="/assets/images/leaderboards/icon-_0009_maniac.png" alt="Maniac" loading="lazy">
        <img src="/assets/images/leaderboards/icon-_0013_the_ghost.png" alt="The Ghost" loading="lazy">
        <img src="/assets/images/leaderboards/icon-_0014_the_insomniac.png" alt="The Insomniac" loading="lazy">
        <img src="/assets/images/leaderboards/icon-_0016_the_packrat.png" alt="The Packrat" loading="lazy">
        <img src="/assets/images/leaderboards/icon-_0017_died_rich.png" alt="Died Rich" loading="lazy">
        <img src="/assets/images/leaderboards/icon-_0010_darwin_award.png" alt="Darwin Award" loading="lazy">
      </div>
      <div class="game-boards__cta">
        <a class="btn btn-primary" href="/leaderboard">The Season Boards</a>
      </div>
    </div>
  </section>

  <!-- CLOSING -->
  <section class="game-outro">
    <div class="container game-outro__inner">
      <p class="game-outro__line">The fog rolls in at dusk. The season clock doesn't care.</p>
      <p class="game-outro__sub">See you in Ravenwood.</p>
    </div>
  </section>

</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

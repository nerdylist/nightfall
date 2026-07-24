<?php
require_once __DIR__ . '/config.php';
$pageTitle = 'Media — The Dead Last';
$pageCss = ['/css/media.css'];
$pageJs = ['/js/media-player.js'];

/**
 * Public media page: an audio player + playlist of the ACTIVE music tracks,
 * as flagged in Keeper > Media (settings.media_active). Only audio entries
 * that still exist on disk are shown; the trailer/video entries are ignored.
 */
$tracks = [];
try {
    $db = grave_db();

    // Active list + admin-authored title overrides (filename → title).
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute(['media_active']);
    $raw = $stmt->fetchColumn();
    $active = ($raw !== false) ? json_decode((string) $raw, true) : [];
    $active = is_array($active) ? $active : [];

    $stmt->execute(['media_titles']);
    $rawT = $stmt->fetchColumn();
    $titleMap = ($rawT !== false) ? json_decode((string) $rawT, true) : [];
    $titleMap = is_array($titleMap) ? $titleMap : [];

    foreach ($active as $p) {
        if (!is_string($p)) {
            continue;
        }
        // Active audio in assets/music/ only, and it must exist on disk.
        if (preg_match('#^/assets/music/.+\.(mp3|ogg|wav|m4a)$#i', $p) && is_file(__DIR__ . $p)) {
            $file = basename($p);
            // Admin title override if set, else prettified filename.
            if (isset($titleMap[$file]) && trim((string) $titleMap[$file]) !== '') {
                $title = trim((string) $titleMap[$file]);
            } else {
                $title = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', pathinfo($file, PATHINFO_FILENAME))));
            }
            $tracks[] = ['src' => $p, 'title' => $title];
        }
    }
} catch (Throwable $e) {
    $tracks = [];
}

include __DIR__ . '/partials/header.php';
?>

<main>
  <section class="media-page">
    <div class="container">
      <?php if (empty($tracks)): ?>
        <div class="card media-empty">
          <p class="text-muted">No tracks are live yet. Check back soon.</p>
        </div>
      <?php else: ?>
        <!-- Deck: the player and its slide-out playlist drawer, centered as a
             unit. Only render the drawer when there's more than one track. -->
        <div class="media-deck<?= count($tracks) > 1 ? '' : ' media-deck--single' ?>" id="media-deck">

          <div class="media-player" id="media-player">
            <!-- Rotated brand label on the left spine. -->
            <span class="media-player__brand" aria-hidden="true">DEADAMP</span>

            <!-- Vertical volume control on the left side. -->
            <div class="media-player__volume">
              <span class="media-player__vol-icon" id="mp-vol-icon" aria-hidden="true">&#128266;</span>
              <input type="range" class="media-player__vol" id="mp-vol" min="0" max="100" value="100" step="1"
                     aria-label="Volume" orient="vertical">
            </div>

            <!-- Now-playing / transport -->
            <div class="media-player__now">
              <p class="media-player__subtitle">Sounds from the world of THE DEAD LAST</p>

              <!-- Live B&W frequency visualizer (Web Audio API). -->
              <div class="media-player__viz-wrap" aria-hidden="true">
                <canvas class="media-player__viz" id="mp-viz" width="720" height="120"></canvas>
              </div>

              <div class="media-player__meta">
                <span class="media-player__eyebrow">Now Playing</span>
                <span class="media-player__track" id="mp-title"><?= htmlspecialchars($tracks[0]['title']) ?></span>
              </div>

              <div class="media-player__scrub">
                <span class="media-player__time" id="mp-current">0:00</span>
                <input type="range" class="media-player__seek" id="mp-seek" min="0" max="100" value="0" step="0.1" aria-label="Seek">
                <span class="media-player__time" id="mp-duration">0:00</span>
              </div>

              <div class="media-player__controls">
                <button type="button" class="media-player__btn" id="mp-prev" aria-label="Previous">&#9198;</button>
                <button type="button" class="media-player__btn media-player__btn--play" id="mp-play" aria-label="Play">&#9654;</button>
                <button type="button" class="media-player__btn" id="mp-next" aria-label="Next">&#9197;</button>
              </div>

              <audio id="mp-audio" preload="metadata" src="<?= htmlspecialchars($tracks[0]['src']) ?>"></audio>
            </div>
          </div>

          <?php if (count($tracks) > 1): ?>
          <!-- Slide-out playlist drawer (beside the player, right). -->
          <aside class="media-drawer" id="mp-drawer" aria-hidden="true">
            <div class="media-drawer__panel">
              <div class="media-drawer__head">
                <span class="media-drawer__title">Playlist</span>
                <span class="media-drawer__count"><?= count($tracks) ?> tracks</span>
              </div>
              <ol class="media-playlist" id="mp-playlist">
                <?php foreach ($tracks as $i => $t): ?>
                <li class="media-playlist__item<?= $i === 0 ? ' is-current' : '' ?>"
                    data-src="<?= htmlspecialchars($t['src']) ?>"
                    data-title="<?= htmlspecialchars($t['title']) ?>"
                    data-index="<?= $i ?>">
                  <span class="media-playlist__num"><?= $i + 1 ?></span>
                  <span class="media-playlist__title"><?= htmlspecialchars($t['title']) ?></span>
                  <span class="media-playlist__eq" aria-hidden="true"><i></i><i></i><i></i></span>
                </li>
                <?php endforeach; ?>
              </ol>
              <div class="media-drawer__foot">
                <span class="media-drawer__foot-text">DEADAMP</span>
              </div>
            </div>
          </aside>

          <!-- Tab handle on the RIGHT of the deck: rests on the player's right
               edge when closed, rides out to the drawer's right edge when open.
               Arrow points right (open ▸) when closed, left (◂ close) when open. -->
          <button type="button" class="media-deck__handle" id="mp-handle" aria-expanded="false" aria-controls="mp-drawer" aria-label="Toggle playlist">
            <span class="media-deck__handle-icon" aria-hidden="true">&#9656;</span>
          </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</main>

<?php include __DIR__ . '/partials/footer.php'; ?>

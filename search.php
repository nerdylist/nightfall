<?php
/**
 * THE DEAD LAST — site search.
 *
 * Wired to the header search form (submits GET ?q=). Substring, case-insensitive
 * matching across four groups:
 *   - Pages       static site pages (hardcoded list)
 *   - Categories  forum categories (name + description)
 *   - Threads     forum threads (title + excerpt)
 *   - Users       forum/site users (username + display name -> profile)
 *
 * Forum content comes from the forum data layer (bbs/config.php + bbs/data/db.php),
 * which is self-contained under bbs/ and attaches the host users table, so a
 * single include covers threads, categories, and users.
 */
require_once __DIR__ . '/config.php';

$q = trim((string) ($_GET['q'] ?? ''));
$qLower = mb_strtolower($q);

/** Case-insensitive substring test. */
function search_hit(string $haystack, string $needleLower): bool
{
    if ($needleLower === '') {
        return false;
    }
    return mb_strpos(mb_strtolower($haystack), $needleLower) !== false;
}

// ---------------------------------------------------------------------------
// Static site pages — title + keywords -> url.
// ---------------------------------------------------------------------------
$pages = [
    ['title' => 'Home',        'url' => '/',            'keywords' => 'home landing start dead last'],
    ['title' => 'The Game',    'url' => '/game',        'keywords' => 'game survival royale play about'],
    ['title' => 'News',        'url' => '/news',        'keywords' => 'news updates announcements patch notes'],
    ['title' => 'Media',       'url' => '/media',       'keywords' => 'media music audio player soundtrack deadamp'],
    ['title' => 'Leaderboard', 'url' => '/leaderboard', 'keywords' => 'leaderboard boards season ranking survival time scores'],
    ['title' => 'Shop',        'url' => '/shop',        'keywords' => 'shop store items buy merch'],
    ['title' => 'Community',   'url' => '/bbs/',        'keywords' => 'community forum bbs threads discussion chat'],
];

$pageResults = [];
if ($q !== '') {
    foreach ($pages as $p) {
        if (search_hit($p['title'], $qLower) || search_hit($p['keywords'], $qLower)) {
            $pageResults[] = $p;
        }
    }
}

// ---------------------------------------------------------------------------
// Forum content — threads, categories, users.
// ---------------------------------------------------------------------------
$categoryResults = [];
$threadResults   = [];
$userResults     = [];

if ($q !== '') {
    require_once __DIR__ . '/bbs/config.php';
    require_once __DIR__ . '/bbs/data/db.php';

    // Categories: name + description.
    foreach (get_categories() as $c) {
        if (search_hit((string) $c['name'], $qLower)
            || search_hit((string) ($c['description'] ?? ''), $qLower)) {
            $categoryResults[] = $c;
        }
    }

    // Threads: title + excerpt. Resolve each match's category for its link/badge.
    $catsById = [];
    foreach (get_categories() as $c) {
        $catsById[(int) $c['id']] = $c;
    }
    foreach (get_threads() as $t) {
        if (search_hit((string) $t['title'], $qLower)
            || search_hit((string) ($t['excerpt'] ?? ''), $qLower)) {
            $t['_category'] = $catsById[(int) $t['category_id']] ?? null;
            $threadResults[] = $t;
        }
    }

    // Users: username + display name.
    foreach (get_users() as $u) {
        if (search_hit((string) $u['username'], $qLower)
            || search_hit((string) ($u['display_name'] ?? ''), $qLower)) {
            $userResults[] = $u;
        }
    }
}

$totalResults = count($pageResults) + count($categoryResults) + count($threadResults) + count($userResults);

$pageTitle = $q !== '' ? ('Search: ' . $q . ' — The Dead Last') : 'Search — The Dead Last';
$pageCss = ['/css/search.css'];

include __DIR__ . '/partials/header.php';
?>
<main class="container search-page">
  <header class="search-head">
    <h1 class="search-title">Search</h1>
    <form class="search-form" method="get" action="/search" role="search">
      <input class="search-form__input field" type="search" name="q" value="<?= htmlspecialchars($q) ?>"
             placeholder="Search pages, threads, categories, people…" aria-label="Search" autofocus>
      <button class="btn btn-primary search-form__submit" type="submit">Search</button>
    </form>
    <?php if ($q !== ''): ?>
      <p class="search-summary">
        <?= $totalResults ?> result<?= $totalResults === 1 ? '' : 's' ?> for
        <strong>&ldquo;<?= htmlspecialchars($q) ?>&rdquo;</strong>
      </p>
    <?php endif; ?>
  </header>

  <?php if ($q === ''): ?>
    <p class="search-empty">Type something above to search the site.</p>
  <?php elseif ($totalResults === 0): ?>
    <p class="search-empty">No matches for &ldquo;<?= htmlspecialchars($q) ?>&rdquo;. Try a different term.</p>
  <?php else: ?>

    <?php if ($pageResults): ?>
      <section class="search-group">
        <h2 class="search-group__title">Pages <span class="search-group__count"><?= count($pageResults) ?></span></h2>
        <ul class="search-list">
          <?php foreach ($pageResults as $p): ?>
            <li class="search-result">
              <a class="search-result__link" href="<?= htmlspecialchars($p['url']) ?>">
                <span class="search-result__name"><?= htmlspecialchars($p['title']) ?></span>
                <span class="search-result__meta"><?= htmlspecialchars($p['url']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($categoryResults): ?>
      <section class="search-group">
        <h2 class="search-group__title">Categories <span class="search-group__count"><?= count($categoryResults) ?></span></h2>
        <ul class="search-list">
          <?php foreach ($categoryResults as $c): ?>
            <li class="search-result">
              <a class="search-result__link" href="/bbs/category/<?= (int) $c['id'] ?>">
                <span class="search-result__name"><?= htmlspecialchars((string) $c['name']) ?></span>
                <?php if (!empty($c['description'])): ?>
                  <span class="search-result__meta"><?= htmlspecialchars((string) $c['description']) ?></span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($threadResults): ?>
      <section class="search-group">
        <h2 class="search-group__title">Threads <span class="search-group__count"><?= count($threadResults) ?></span></h2>
        <ul class="search-list">
          <?php foreach ($threadResults as $t): ?>
            <li class="search-result">
              <a class="search-result__link" href="/bbs/thread/<?= (int) $t['id'] ?>">
                <span class="search-result__name"><?= htmlspecialchars((string) $t['title']) ?></span>
                <span class="search-result__meta">
                  <?php if ($t['_category'] !== null): ?>
                    in <?= htmlspecialchars((string) $t['_category']['name']) ?> ·
                  <?php endif; ?>
                  <?= (int) ($t['replies'] ?? 0) ?> replies
                </span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($userResults): ?>
      <section class="search-group">
        <h2 class="search-group__title">People <span class="search-group__count"><?= count($userResults) ?></span></h2>
        <ul class="search-list">
          <?php foreach ($userResults as $u): ?>
            <?php $display = (string) ($u['display_name'] ?: $u['username']); ?>
            <li class="search-result">
              <a class="search-result__link" href="/bbs/profile/<?= urlencode((string) $u['username']) ?>">
                <span class="search-result__name"><?= htmlspecialchars($display) ?></span>
                <span class="search-result__meta">@<?= htmlspecialchars((string) $u['username']) ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>

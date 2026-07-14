<?php
require_once __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!grave_is_admin()) {
    header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/keeper/dashboard.php'));
    exit;
}

/**
 * Keeper > Items — view the in-game item catalog.
 *
 * The catalog lives in the host `items` table. The game POSTs its full
 * registry to /api/items (Bearer, replace-all); this page READS that table and
 * lists it. The "Update" button re-fetches the public GET /api/items
 * (env GAME_ITEMS_URL) and mirrors it into the local table — handy to pull the
 * production catalog into a local/staging view, or to force a refresh.
 * Read-only display otherwise (editing the catalog is the game's job).
 */

/** Known item columns (mirrors api/items ITEM_COLUMNS; extra holds the rest). */
const KEEPER_ITEM_COLUMNS = [
    'item_id', 'display_name', 'category', 'rarity', 'stackable', 'max_stack',
    'power', 'weight_kg', 'value', 'durability', 'description', 'used_to',
    'thumbnail', 'model',
];

/** Fetch the item catalog JSON from the public GET endpoint. */
function keeper_fetch_items(string $url): array
{
    $ctx = stream_context_create([
        'http'  => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
        'https' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        throw new RuntimeException('Could not reach the items endpoint.');
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($status !== 0 && ($status < 200 || $status >= 300)) {
        throw new RuntimeException("Items endpoint returned HTTP {$status}.");
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Items endpoint did not return valid JSON.');
    }

    $list = array_key_exists('items', $data) && is_array($data['items']) ? $data['items'] : $data;
    $out = [];
    foreach ($list as $it) {
        if (is_array($it) && !empty($it['item_id'])) {
            $out[] = $it;
        }
    }
    if (!$out) {
        throw new RuntimeException('Items endpoint returned no items.');
    }

    return $out;
}

/** Replace the local items table with the fetched list (same shape as the POST ingest). */
function keeper_replace_items(PDO $db, array $items): int
{
    $db->beginTransaction();
    try {
        $db->exec('DELETE FROM items');
        $stmt = $db->prepare(
            'INSERT INTO items
                (item_id, display_name, category, rarity, stackable, max_stack, power,
                 weight_kg, value, durability, description, used_to, thumbnail, model,
                 extra, updated_at)
             VALUES
                (:item_id, :display_name, :category, :rarity, :stackable, :max_stack, :power,
                 :weight_kg, :value, :durability, :description, :used_to, :thumbnail, :model,
                 :extra, CURRENT_TIMESTAMP)'
        );

        $n = 0;
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['item_id'])) {
                continue;
            }
            $extra = [];
            foreach ($item as $k => $v) {
                if (!in_array($k, KEEPER_ITEM_COLUMNS, true) && $k !== 'extra') {
                    $extra[$k] = $v;
                }
            }
            // Preserve any existing extra blob the endpoint already nested.
            if (isset($item['extra']) && is_array($item['extra'])) {
                $extra = array_merge($item['extra'], $extra);
            }

            $stmt->execute([
                'item_id'      => (string) $item['item_id'],
                'display_name' => (string) ($item['display_name'] ?? $item['item_id']),
                'category'     => $item['category']   ?? null,
                'rarity'       => $item['rarity']     ?? null,
                'stackable'    => !empty($item['stackable']) ? 1 : 0,
                'max_stack'    => isset($item['max_stack']) ? (int) $item['max_stack'] : 1,
                'power'        => isset($item['power']) ? (int) $item['power'] : null,
                'weight_kg'    => isset($item['weight_kg']) ? (float) $item['weight_kg'] : null,
                'value'        => isset($item['value']) ? (int) $item['value'] : null,
                'durability'   => isset($item['durability']) ? (int) $item['durability'] : null,
                'description'  => $item['description'] ?? null,
                'used_to'      => $item['used_to']     ?? null,
                'thumbnail'    => $item['thumbnail']   ?? null,
                'model'        => $item['model']       ?? null,
                'extra'        => $extra ? json_encode($extra) : null,
            ]);
            $n++;
        }

        $db->commit();
        return $n;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

// Keeper-scoped CSRF token (separate from the forum's csrf_token()).
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

$db = grave_db();

// Ensure the table exists even before the migration has run (idempotent).
$db->exec(
    'CREATE TABLE IF NOT EXISTS items (
        item_id TEXT PRIMARY KEY, display_name TEXT, category TEXT, rarity TEXT,
        stackable INTEGER NOT NULL DEFAULT 0, max_stack INTEGER NOT NULL DEFAULT 1,
        power INTEGER, weight_kg REAL, value INTEGER, durability INTEGER,
        description TEXT, used_to TEXT, thumbnail TEXT, model TEXT, extra TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';

    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/items.php');
        exit;
    }

    if (isset($_POST['update_items'])) {
        $url = trim((string) env('GAME_ITEMS_URL', ''));
        if ($url === '') {
            $url = 'https://thedeadlast.com/api/items'; // sensible default
        }

        try {
            $items = keeper_fetch_items($url);
            $n     = keeper_replace_items($db, $items);
            $_SESSION['keeper_flash'] = "Items updated — {$n} item(s) synced.";
        } catch (Throwable $e) {
            $_SESSION['keeper_flash'] = 'Items update failed: ' . $e->getMessage();
        }

        header('Location: /keeper/items.php');
        exit;
    }

    header('Location: /keeper/items.php');
    exit;
}

$pageTitle = 'Items — Keeper';
$pageCss = ['/css/keeper-items.css'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$items = $db->query(
    'SELECT item_id, display_name, category, rarity, stackable, max_stack,
            power, weight_kg, value, durability, description, used_to, thumbnail, model
     FROM items ORDER BY category, display_name, item_id'
)->fetchAll();

$itemsUrl = trim((string) env('GAME_ITEMS_URL', '')) ?: 'https://thedeadlast.com/api/items';

// Group by category for display.
$byCategory = [];
foreach ($items as $it) {
    $cat = (string) ($it['category'] ?? '') ?: 'Uncategorized';
    $byCategory[$cat][] = $it;
}
ksort($byCategory);

/** Rarity -> css modifier for the chip colour. */
function keeper_rarity_class(?string $rarity): string
{
    $r = strtolower(trim((string) $rarity));
    return in_array($r, ['common', 'uncommon', 'rare', 'epic', 'legendary'], true)
        ? 'keeper-items-rarity--' . $r
        : 'keeper-items-rarity--common';
}
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Items</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <div class="card keeper-table-card">
      <div class="keeper-items-bar">
        <p class="text-muted keeper-items-hint">
          The in-game item catalog (<strong><?= count($items) ?></strong> item<?= count($items) === 1 ? '' : 's' ?>). The game pushes its registry to <code>/api/items</code>; press <strong>Update</strong> to re-fetch the catalog from <code><?= htmlspecialchars($itemsUrl) ?></code>.
        </p>
        <form method="post" action="/keeper/items.php" class="keeper-items-form">
          <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
          <button type="submit" name="update_items" value="1" class="btn btn-primary">Update</button>
        </form>
      </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="card keeper-table-card">
      <p class="text-muted">No items yet. Press <strong>Update</strong> to pull the catalog, or run the game's item export to POST it to <code>/api/items</code>.</p>
    </div>
    <?php else: ?>
      <?php foreach ($byCategory as $cat => $rows): ?>
      <div class="card keeper-table-card">
        <h2 class="keeper-table-card__heading"><?= htmlspecialchars($cat) ?> <span class="keeper-items-count"><?= count($rows) ?></span></h2>
        <div class="keeper-table-scroll">
          <table class="keeper-table keeper-items-table">
            <thead>
              <tr>
                <th></th>
                <th>Item</th>
                <th>Rarity</th>
                <th>Stack</th>
                <th>Power</th>
                <th>Weight</th>
                <th>Value</th>
                <th>Durability</th>
                <th>Description</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $it): ?>
              <tr>
                <td class="keeper-items-thumb-cell">
                  <?php if (!empty($it['thumbnail'])): ?>
                    <img class="keeper-items-thumb" src="/<?= htmlspecialchars(ltrim((string) $it['thumbnail'], '/')) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
                  <?php endif; ?>
                </td>
                <td>
                  <span class="keeper-items-name"><?= htmlspecialchars((string) $it['display_name']) ?></span>
                  <span class="keeper-items-id"><?= htmlspecialchars((string) $it['item_id']) ?></span>
                </td>
                <td><span class="keeper-items-rarity <?= keeper_rarity_class($it['rarity']) ?>"><?= htmlspecialchars((string) ($it['rarity'] ?? '—')) ?></span></td>
                <td><?= !empty($it['stackable']) ? (int) $it['max_stack'] : '<span class="text-muted">no</span>' ?></td>
                <td><?= $it['power'] !== null ? (int) $it['power'] : '<span class="text-muted">—</span>' ?></td>
                <td><?= $it['weight_kg'] !== null ? htmlspecialchars(rtrim(rtrim(number_format((float) $it['weight_kg'], 2), '0'), '.')) . ' kg' : '<span class="text-muted">—</span>' ?></td>
                <td><?= $it['value'] !== null ? '$' . (int) $it['value'] : '<span class="text-muted">—</span>' ?></td>
                <td><?= $it['durability'] !== null ? (int) $it['durability'] : '<span class="text-muted">—</span>' ?></td>
                <td class="keeper-items-desc"><?= htmlspecialchars((string) ($it['description'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

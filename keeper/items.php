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

/** Dropdown options for the item editor. New values can be added over time;
 *  these seed the pickers and match the current game catalog. */
const KEEPER_ITEM_CATEGORIES = ['Ammo', 'Food', 'Fuel', 'Light', 'Material', 'Medicine', 'Tool', 'Weapon'];
const KEEPER_ITEM_RARITIES   = ['Common', 'Uncommon', 'Rare', 'Epic', 'Legendary'];

/** Normalize an item_id: lowercase, a-z0-9 and underscores (game convention). */
function keeper_item_slug(string $id): string
{
    $id = strtolower(trim($id));
    $id = preg_replace('/[^a-z0-9]+/', '_', $id);

    return trim((string) $id, '_');
}

/**
 * Validate + store an uploaded item icon under assets/items/. Returns the
 * stored web path on success, '' if no file was uploaded, or throws
 * RuntimeException on a bad upload. MIME sniffed server-side (mirrors the
 * character avatar upload).
 */
function keeper_store_item_icon(array $file, string $slug): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Icon upload failed (code ' . (int) $file['error'] . ').');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Icon too large (max 5 MB).');
    }
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported image type (use PNG, JPG, WebP, or GIF).');
    }
    if (getimagesize($tmp) === false) {
        throw new RuntimeException('That file is not a valid image.');
    }
    $ext = $allowed[$mime];
    $dir = __DIR__ . '/../assets/items';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the icon directory.');
    }
    $slug = $slug !== '' ? $slug : 'item';
    $name = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded icon.');
    }
    return 'assets/items/' . $name;
}

/**
 * Weapon-stats fields (offload FirearmSpec). Each: [post-name, type]. `ammo_id`
 * is a string ref; the rest are numeric. Stored together as weapon_json and
 * emitted as the nested "weapon" object (present only when at least one is set).
 */
const KEEPER_WEAPON_FIELDS = [
    'ammo_id'         => 'str',
    'capacity'        => 'int',
    'damage'          => 'int',
    'pellets'         => 'int',
    'spread_deg'      => 'float',
    'range'           => 'float',
    'reload_seconds'  => 'float',
    'cooldown'        => 'float',
    'noise_radius'    => 'float',
    'recoil_deg'      => 'float',
    'flash_intensity' => 'float',
    'flash_seconds'   => 'float',
    'crit_chance'     => 'float',
    'crit_damage'     => 'int',
];

/**
 * Assemble the weapon block JSON from POST (weapon_* fields), or null if none
 * were filled in (so a non-weapon item stores no weapon block).
 */
function keeper_item_weapon_json(array $post): ?string
{
    $w = [];
    foreach (KEEPER_WEAPON_FIELDS as $key => $type) {
        $raw = trim((string) ($post['weapon_' . $key] ?? ''));
        if ($raw === '') {
            continue;
        }
        $w[$key] = $type === 'str' ? $raw : ($type === 'int' ? (int) $raw : 0 + $raw);
    }

    return $w ? json_encode($w, JSON_UNESCAPED_SLASHES) : null;
}

/**
 * Assemble the damage_bands JSON from the parallel POST arrays (dband_from[],
 * dband_to[], dband_mode[], dband_value[]). Same shape as the character HP
 * bands. Drops blank rows; returns null when there are no valid bands.
 */
function keeper_item_bands_json(array $post): ?string
{
    $from  = (array) ($post['dband_from'] ?? []);
    $to    = (array) ($post['dband_to'] ?? []);
    $mode  = (array) ($post['dband_mode'] ?? []);
    $value = (array) ($post['dband_value'] ?? []);

    $bands = [];
    foreach ($from as $i => $f) {
        $f = trim((string) $f);
        $v = trim((string) ($value[$i] ?? ''));
        if ($f === '' || $v === '') {
            continue;
        }
        $t = trim((string) ($to[$i] ?? ''));
        $m = ($mode[$i] ?? 'flat') === 'pct' ? 'pct' : 'flat';
        $bands[] = [
            'from'  => max(1, (int) $f),
            'to'    => $t === '' ? null : max(1, (int) $t),
            'mode'  => $m,
            'value' => 0 + $v,
        ];
    }
    if (!$bands) {
        return null;
    }
    usort($bands, fn ($a, $b) => $a['from'] <=> $b['from']);

    return json_encode($bands, JSON_UNESCAPED_SLASHES);
}

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
// Runtime-offload columns (017) — added idempotently for DBs that predate it.
foreach ([
    'visual_key' => 'TEXT', 'active' => 'INTEGER NOT NULL DEFAULT 1',
    'weapon_json' => 'TEXT', 'wave_min' => 'INTEGER', 'wave_max' => 'INTEGER',
    'rarity_weight' => 'INTEGER', 'damage_bands' => 'TEXT', 'icon_path' => 'TEXT',
] as $col => $decl) {
    try { $db->exec("ALTER TABLE items ADD COLUMN {$col} {$decl}"); }
    catch (Throwable $e) { /* already exists */ }
}

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

    // --- Delete an item ---
    if (isset($_POST['delete_item'])) {
        $id = keeper_item_slug((string) ($_POST['item_id'] ?? ''));
        if ($id !== '') {
            // Remove the uploaded icon file, if any, before deleting the row.
            $cur = $db->prepare('SELECT icon_path FROM items WHERE item_id = ?');
            $cur->execute([$id]);
            $icon = $cur->fetchColumn();
            if ($icon) {
                $abs = __DIR__ . '/../' . ltrim((string) $icon, '/');
                if (is_file($abs)) { @unlink($abs); }
            }
            $stmt = $db->prepare('DELETE FROM items WHERE item_id = ?');
            $stmt->execute([$id]);
            $_SESSION['keeper_flash'] = "Deleted item \"{$id}\".";
        }
        header('Location: /keeper/items.php');
        exit;
    }

    // --- Create or update an item (full form) ---
    if (isset($_POST['save_item'])) {
        $original = keeper_item_slug((string) ($_POST['original_item_id'] ?? '')); // set when editing
        $id       = keeper_item_slug((string) ($_POST['item_id'] ?? ''));
        $name     = trim((string) ($_POST['display_name'] ?? ''));

        // Validate the extra JSON blob (optional).
        $extraRaw = trim((string) ($_POST['extra'] ?? ''));
        $extra    = null;
        if ($extraRaw !== '') {
            $decoded = json_decode($extraRaw, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['keeper_flash'] = 'Extra must be valid JSON (or blank). Nothing was saved.';
                header('Location: /keeper/items.php?edit=' . urlencode($original !== '' ? $original : $id));
                exit;
            }
            $extra = json_encode($decoded);
        }

        $errors = [];
        if ($id === '') {
            $errors[] = 'Item ID is required (letters, numbers, underscores).';
        }
        if ($name === '') {
            $name = $id; // fall back to the id as the display name
        }

        // On create (or when the id changed), the target id must be free.
        if (!$errors && ($original === '' || $original !== $id)) {
            $check = $db->prepare('SELECT 1 FROM items WHERE item_id = ?');
            $check->execute([$id]);
            if ($check->fetchColumn() !== false) {
                $errors[] = "An item with ID \"{$id}\" already exists.";
            }
        }

        if ($errors) {
            $_SESSION['keeper_flash'] = implode(' ', $errors);
            header('Location: /keeper/items.php' . ($original !== '' ? '?edit=' . urlencode($original) : '#add'));
            exit;
        }

        // Assemble the weapon stats block (offload). Only fields with a value
        // are kept; an item with no weapon inputs stores null (not a weapon).
        $weaponJson = keeper_item_weapon_json($_POST);
        // Wave dials (all optional; absent = game default).
        $damageBands = keeper_item_bands_json($_POST);

        // Item icon upload (optional). On edit, a new file replaces the stored
        // path; no file keeps the existing one.
        try {
            $iconNew = keeper_store_item_icon($_FILES['icon'] ?? [], $id);
        } catch (Throwable $e) {
            $_SESSION['keeper_flash'] = 'Icon error: ' . $e->getMessage();
            header('Location: /keeper/items.php?edit=' . urlencode($original !== '' ? $original : $id));
            exit;
        }
        $iconPath = $iconNew;
        if ($iconNew === '') {
            // Keep whatever's stored for this item (edit with no new upload).
            $cur = $db->prepare('SELECT icon_path FROM items WHERE item_id = ?');
            $cur->execute([$original !== '' ? $original : $id]);
            $iconPath = $cur->fetchColumn() ?: null;
        }

        $fields = [
            'item_id'      => $id,
            'display_name' => $name,
            'category'     => trim((string) ($_POST['category'] ?? '')) ?: null,
            'rarity'       => trim((string) ($_POST['rarity'] ?? '')) ?: null,
            'stackable'    => !empty($_POST['stackable']) ? 1 : 0,
            'max_stack'    => max(1, (int) ($_POST['max_stack'] ?? 1)),
            'power'        => ($_POST['power'] ?? '') === '' ? null : (int) $_POST['power'],
            'weight_kg'    => ($_POST['weight_kg'] ?? '') === '' ? null : (float) $_POST['weight_kg'],
            'value'        => ($_POST['value'] ?? '') === '' ? null : (int) $_POST['value'],
            'durability'   => ($_POST['durability'] ?? '') === '' ? null : (int) $_POST['durability'],
            'description'  => trim((string) ($_POST['description'] ?? '')) ?: null,
            'used_to'      => trim((string) ($_POST['used_to'] ?? '')) ?: null,
            'thumbnail'    => trim((string) ($_POST['thumbnail'] ?? '')) ?: null,
            'model'        => trim((string) ($_POST['model'] ?? '')) ?: null,
            'extra'        => $extra,
            // Offload columns (migration 017).
            'visual_key'    => trim((string) ($_POST['visual_key'] ?? '')) ?: null,
            'active'        => !empty($_POST['active']) ? 1 : 0,
            'weapon_json'   => $weaponJson,
            'wave_min'      => ($_POST['wave_min'] ?? '') === '' ? null : max(1, (int) $_POST['wave_min']),
            'wave_max'      => ($_POST['wave_max'] ?? '') === '' ? null : max(1, (int) $_POST['wave_max']),
            'rarity_weight' => ($_POST['rarity_weight'] ?? '') === '' ? null : max(0, (int) $_POST['rarity_weight']),
            'damage_bands'  => $damageBands,
            'icon_path'     => $iconPath ?: null,
        ];

        if ($original !== '' && $original === $id) {
            // Update in place.
            $stmt = $db->prepare(
                'UPDATE items SET display_name=:display_name, category=:category, rarity=:rarity,
                    stackable=:stackable, max_stack=:max_stack, power=:power, weight_kg=:weight_kg,
                    value=:value, durability=:durability, description=:description, used_to=:used_to,
                    thumbnail=:thumbnail, model=:model, extra=:extra,
                    visual_key=:visual_key, active=:active, weapon_json=:weapon_json,
                    wave_min=:wave_min, wave_max=:wave_max, rarity_weight=:rarity_weight,
                    damage_bands=:damage_bands, icon_path=:icon_path, updated_at=CURRENT_TIMESTAMP
                 WHERE item_id=:item_id'
            );
            $stmt->execute($fields);
            $_SESSION['keeper_flash'] = "Saved item \"{$id}\".";
        } else {
            // Insert (create, or rename = insert new + delete old).
            $stmt = $db->prepare(
                'INSERT INTO items
                    (item_id, display_name, category, rarity, stackable, max_stack, power,
                     weight_kg, value, durability, description, used_to, thumbnail, model, extra,
                     visual_key, active, weapon_json, wave_min, wave_max, rarity_weight, damage_bands, icon_path, updated_at)
                 VALUES
                    (:item_id, :display_name, :category, :rarity, :stackable, :max_stack, :power,
                     :weight_kg, :value, :durability, :description, :used_to, :thumbnail, :model, :extra,
                     :visual_key, :active, :weapon_json, :wave_min, :wave_max, :rarity_weight, :damage_bands, :icon_path, CURRENT_TIMESTAMP)'
            );
            $stmt->execute($fields);
            if ($original !== '' && $original !== $id) {
                $del = $db->prepare('DELETE FROM items WHERE item_id = ?');
                $del->execute([$original]);
                $_SESSION['keeper_flash'] = "Renamed \"{$original}\" to \"{$id}\".";
            } else {
                $_SESSION['keeper_flash'] = "Created item \"{$id}\".";
            }
        }

        header('Location: /keeper/items.php');
        exit;
    }

    header('Location: /keeper/items.php');
    exit;
}

$pageTitle = 'Items — Keeper';
$pageCss = ['/css/keeper-items.css'];
$pageJs = ['/js/keeper-items.js'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

$items = $db->query(
    'SELECT item_id, display_name, category, rarity, stackable, max_stack,
            power, weight_kg, value, durability, description, used_to, thumbnail, model,
            icon_path
     FROM items ORDER BY category, display_name, item_id'
)->fetchAll();

// If ?edit=<item_id>, load that row (all columns incl. extra) to prefill the
// form; otherwise the form is a blank "add new item".
$editItem = null;
$editId = keeper_item_slug((string) ($_GET['edit'] ?? ''));
if ($editId !== '') {
    $stmt = $db->prepare('SELECT * FROM items WHERE item_id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch() ?: null;
}

/** Prefill value for a form field from the item being edited (blank on add). */
function keeper_item_val(?array $item, string $key): string
{
    if ($item === null || !array_key_exists($key, $item) || $item[$key] === null) {
        return '';
    }
    return (string) $item[$key];
}

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

    <!-- Button bar: catalog count + actions. -->
    <div class="keeper-items-toolbar">
      <p class="text-muted keeper-items-toolbar__count"><strong><?= count($items) ?></strong> item<?= count($items) === 1 ? '' : 's' ?> in the catalog</p>
      <div class="keeper-items-toolbar__actions">
        <div class="keeper-items-viewtoggle" role="group" aria-label="View">
          <button type="button" class="keeper-items-viewbtn is-on" data-view="grid" title="Grid view" aria-label="Grid view">
            <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/grip.svg" alt="">
          </button>
          <button type="button" class="keeper-items-viewbtn" data-view="list" title="List view" aria-label="List view">
            <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/list.svg" alt="">
          </button>
        </div>
        <button type="button" class="btn btn-primary" data-open-item-modal>Add Item</button>
        <form method="post" action="/keeper/items.php" class="keeper-items-form">
          <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
          <button type="submit" name="update_items" value="1" class="btn btn-ghost">Update</button>
        </form>
      </div>
    </div>

    <!-- Add / edit item modal. Opened by "Add Item", or auto-opens on ?edit=. -->
    <div class="keeper-modal<?= $editItem ? ' is-open' : '' ?>" id="item-modal" role="dialog" aria-modal="true" aria-labelledby="item-modal-title"<?= $editItem ? '' : ' hidden' ?>>
      <div class="keeper-modal__backdrop" data-close-item-modal></div>
      <div class="keeper-modal__panel" role="document">
        <div class="keeper-modal__head">
          <h2 class="keeper-modal__title" id="item-modal-title"><?= $editItem ? 'Edit Item' : 'Add Item' ?></h2>
          <a href="/keeper/items.php" class="keeper-modal__close" aria-label="Close" data-close-item-modal>&times;</a>
        </div>
        <p class="text-muted keeper-modal__desc"><?= $editItem ? 'Update this item. Item ID is lowercase letters, numbers and underscores.' : 'Add a new item to the system. Item ID is lowercase letters, numbers and underscores; the game ingests this catalog at build time.' ?></p>

      <form method="post" action="/keeper/items.php" class="keeper-items-editor" enctype="multipart/form-data">
        <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
        <?php if ($editItem): ?>
        <input type="hidden" name="original_item_id" value="<?= htmlspecialchars((string) $editItem['item_id']) ?>">
        <?php endif; ?>

        <div class="keeper-items-grid">
          <label class="keeper-items-field">
            <span class="keeper-items-label">Item ID</span>
            <input class="field" type="text" name="item_id" value="<?= htmlspecialchars(keeper_item_val($editItem, 'item_id')) ?>" placeholder="ammo_pistol" required>
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Display Name</span>
            <input class="field" type="text" name="display_name" value="<?= htmlspecialchars(keeper_item_val($editItem, 'display_name')) ?>" placeholder="Ammo Pistol">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Category</span>
            <input class="field" type="text" name="category" list="item-categories" value="<?= htmlspecialchars(keeper_item_val($editItem, 'category')) ?>" placeholder="Weapon">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Rarity</span>
            <input class="field" type="text" name="rarity" list="item-rarities" value="<?= htmlspecialchars(keeper_item_val($editItem, 'rarity')) ?>" placeholder="Common">
          </label>

          <label class="keeper-items-field keeper-items-field--check">
            <input type="checkbox" name="stackable" value="1" <?= (int) keeper_item_val($editItem, 'stackable') === 1 ? 'checked' : '' ?>>
            <span class="keeper-items-label">Stackable</span>
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Max Stack</span>
            <input class="field" type="number" name="max_stack" min="1" value="<?= htmlspecialchars(keeper_item_val($editItem, 'max_stack') ?: '1') ?>">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Power</span>
            <input class="field" type="number" name="power" value="<?= htmlspecialchars(keeper_item_val($editItem, 'power')) ?>" placeholder="0">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Weight (kg)</span>
            <input class="field" type="number" step="0.01" name="weight_kg" value="<?= htmlspecialchars(keeper_item_val($editItem, 'weight_kg')) ?>" placeholder="0.3">
          </label>

          <label class="keeper-items-field">
            <span class="keeper-items-label">Value ($)</span>
            <input class="field" type="number" name="value" value="<?= htmlspecialchars(keeper_item_val($editItem, 'value')) ?>" placeholder="15">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Durability</span>
            <input class="field" type="number" name="durability" value="<?= htmlspecialchars(keeper_item_val($editItem, 'durability')) ?>" placeholder="100">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Thumbnail path</span>
            <input class="field" type="text" name="thumbnail" value="<?= htmlspecialchars(keeper_item_val($editItem, 'thumbnail')) ?>" placeholder="icons/items/ammo_pistol.png">
          </label>
          <label class="keeper-items-field">
            <span class="keeper-items-label">Model ref</span>
            <input class="field" type="text" name="model" value="<?= htmlspecialchars(keeper_item_val($editItem, 'model')) ?>" placeholder="Models/Items/Items.fbx#Item_ammo_pistol">
          </label>
        </div>

        <label class="keeper-items-field keeper-items-field--full">
          <span class="keeper-items-label">Description</span>
          <textarea class="field" name="description" rows="2" placeholder="A box of pistol rounds."><?= htmlspecialchars(keeper_item_val($editItem, 'description')) ?></textarea>
        </label>
        <label class="keeper-items-field keeper-items-field--full">
          <span class="keeper-items-label">Used to</span>
          <input class="field" type="text" name="used_to" value="<?= htmlspecialchars(keeper_item_val($editItem, 'used_to')) ?>" placeholder="Reload your pistol">
        </label>
        <label class="keeper-items-field">
          <span class="keeper-items-label">Visual key</span>
          <input class="field" type="text" name="visual_key" value="<?= htmlspecialchars(keeper_item_val($editItem, 'visual_key')) ?>" placeholder="revolver (baked prefab)">
        </label>
        <label class="keeper-items-field keeper-items-icon">
          <span class="keeper-items-label">Icon image</span>
          <?php if ($editItem && !empty($editItem['icon_path'])): ?>
            <img class="keeper-items-icon__thumb" src="/<?= htmlspecialchars(ltrim((string) $editItem['icon_path'], '/')) ?>" alt="">
          <?php endif; ?>
          <input class="field" type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/gif">
        </label>
        <label class="keeper-items-field keeper-items-field--check">
          <input type="checkbox" name="active" value="1" <?= ($editItem === null || (int) keeper_item_val($editItem, 'active') === 1) ? 'checked' : '' ?>>
          <span class="keeper-items-label">Active (in the runtime loot feed)</span>
        </label>

        <?php
          // Decode stored weapon block + damage bands for pre-fill.
          $weapon = [];
          if ($editItem && !empty($editItem['weapon_json'])) {
              $wd = json_decode((string) $editItem['weapon_json'], true);
              if (is_array($wd)) { $weapon = $wd; }
          }
          $dbands = [];
          if ($editItem && !empty($editItem['damage_bands'])) {
              $bd = json_decode((string) $editItem['damage_bands'], true);
              if (is_array($bd)) { $dbands = $bd; }
          }
          $wv = fn (string $k) => htmlspecialchars((string) ($weapon[$k] ?? ''));
        ?>

        <fieldset class="keeper-items-wave keeper-items-field--full">
          <legend>Weapon stats <span class="keeper-items-opt">only for weapons — becomes the “weapon” block in the feed</span></legend>
          <div class="keeper-items-weapongrid">
            <label><span>Ammo id</span><input class="field" type="text" name="weapon_ammo_id" value="<?= $wv('ammo_id') ?>" placeholder="rounds_38"></label>
            <label><span>Capacity</span><input class="field" type="number" name="weapon_capacity" value="<?= $wv('capacity') ?>" min="0"></label>
            <label><span>Damage</span><input class="field" type="number" name="weapon_damage" value="<?= $wv('damage') ?>" min="0"></label>
            <label><span>Pellets</span><input class="field" type="number" name="weapon_pellets" value="<?= $wv('pellets') ?>" min="1"></label>
            <label><span>Spread°</span><input class="field" type="number" step="any" name="weapon_spread_deg" value="<?= $wv('spread_deg') ?>"></label>
            <label><span>Range</span><input class="field" type="number" step="any" name="weapon_range" value="<?= $wv('range') ?>"></label>
            <label><span>Reload s</span><input class="field" type="number" step="any" name="weapon_reload_seconds" value="<?= $wv('reload_seconds') ?>"></label>
            <label><span>Cooldown</span><input class="field" type="number" step="any" name="weapon_cooldown" value="<?= $wv('cooldown') ?>"></label>
            <label><span>Noise r</span><input class="field" type="number" step="any" name="weapon_noise_radius" value="<?= $wv('noise_radius') ?>"></label>
            <label><span>Recoil°</span><input class="field" type="number" step="any" name="weapon_recoil_deg" value="<?= $wv('recoil_deg') ?>"></label>
            <label><span>Flash int</span><input class="field" type="number" step="any" name="weapon_flash_intensity" value="<?= $wv('flash_intensity') ?>"></label>
            <label><span>Flash s</span><input class="field" type="number" step="any" name="weapon_flash_seconds" value="<?= $wv('flash_seconds') ?>"></label>
            <label><span>Crit %</span><input class="field" type="number" step="any" name="weapon_crit_chance" value="<?= $wv('crit_chance') ?>" placeholder="0.08"></label>
            <label><span>Crit dmg</span><input class="field" type="number" name="weapon_crit_damage" value="<?= $wv('crit_damage') ?>"></label>
          </div>
        </fieldset>

        <fieldset class="keeper-items-wave keeper-items-field--full">
          <legend>Wave scaling <span class="keeper-items-opt">optional — loot-pool eligibility + per-wave growth</span></legend>
          <div class="keeper-items-wavegrid">
            <label><span>Wave min</span><input class="field" type="number" name="wave_min" value="<?= htmlspecialchars(keeper_item_val($editItem, 'wave_min')) ?>" placeholder="1" min="1"></label>
            <label><span>Wave max</span><input class="field" type="number" name="wave_max" value="<?= htmlspecialchars(keeper_item_val($editItem, 'wave_max')) ?>" placeholder="∞" min="1"></label>
            <label><span>Rarity weight</span><input class="field" type="number" name="rarity_weight" value="<?= htmlspecialchars(keeper_item_val($editItem, 'rarity_weight')) ?>" placeholder="game default" min="0"></label>
          </div>

          <div class="keeper-items-bands" data-bands>
            <div class="keeper-items-bands__head"><span>Damage bands</span><span class="keeper-items-opt">per-wave growth (usually flat for weapons)</span></div>
            <div class="keeper-items-bands__rows" data-bands-rows>
              <?php
                $renderDBand = function ($b) {
                    $from = htmlspecialchars((string) ($b['from'] ?? ''));
                    $to   = ($b['to'] ?? null) === null ? '' : htmlspecialchars((string) $b['to']);
                    $mode = ($b['mode'] ?? 'flat') === 'pct' ? 'pct' : 'flat';
                    $val  = htmlspecialchars((string) ($b['value'] ?? ''));
                    ?>
                    <div class="keeper-items-band" data-band-row>
                      <input class="field" type="number" name="dband_from[]" value="<?= $from ?>" placeholder="From" min="1">
                      <input class="field" type="number" name="dband_to[]" value="<?= $to ?>" placeholder="To (∞)" min="1">
                      <select class="field" name="dband_mode[]" aria-label="Mode">
                        <option value="flat" <?= $mode === 'flat' ? 'selected' : '' ?>>flat / wave</option>
                        <option value="pct"  <?= $mode === 'pct'  ? 'selected' : '' ?>>% / wave</option>
                      </select>
                      <input class="field" type="number" name="dband_value[]" value="<?= $val ?>" placeholder="Value" step="any">
                      <button type="button" class="keeper-icon-btn keeper-icon-btn--danger" data-band-remove title="Remove band" aria-label="Remove band">
                        <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/xmark.svg" alt="">
                      </button>
                    </div>
                    <?php
                };
                foreach ($dbands as $b) { $renderDBand($b); }
              ?>
            </div>
            <template data-band-template><?php $renderDBand([]); ?></template>
            <button type="button" class="btn btn-ghost keeper-items-band-add" data-band-add>+ Add band</button>
          </div>
        </fieldset>

        <label class="keeper-items-field keeper-items-field--full">
          <span class="keeper-items-label">Extra (JSON, optional)</span>
          <textarea class="field keeper-items-mono" name="extra" rows="2" placeholder='{"key": "value"}'><?= htmlspecialchars(keeper_item_val($editItem, 'extra')) ?></textarea>
        </label>

        <div class="keeper-items-actions">
          <a href="/keeper/items.php" class="btn btn-ghost" data-close-item-modal>Cancel</a>
          <button type="submit" name="save_item" value="1" class="btn btn-primary"><?= $editItem ? 'Save Item' : 'Add Item' ?></button>
        </div>
      </form>
      </div><!-- /.keeper-modal__panel -->
    </div><!-- /.keeper-modal -->

    <datalist id="item-categories"><?php foreach (KEEPER_ITEM_CATEGORIES as $c): ?><option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?></datalist>
    <datalist id="item-rarities"><?php foreach (KEEPER_ITEM_RARITIES as $r): ?><option value="<?= htmlspecialchars($r) ?>"><?php endforeach; ?></datalist>

    <?php if (empty($items)): ?>
    <div class="card keeper-table-card">
      <p class="text-muted">No items yet. Press <strong>Update</strong> to pull the catalog, or run the game's item export to POST it to <code>/api/items</code>.</p>
    </div>
    <?php else: ?>
      <!-- View mode (grid default) is applied to this wrapper by keeper-items.js
           from localStorage; each category renders both a grid and a list, CSS
           shows one. -->
      <div class="keeper-items-catalog" data-items-catalog data-view="grid">
      <?php foreach ($byCategory as $cat => $rows): ?>
      <div class="card keeper-table-card">
        <h2 class="keeper-table-card__heading"><?= htmlspecialchars($cat) ?> <span class="keeper-items-count"><?= count($rows) ?></span></h2>

        <!-- Grid view: icon + name cards; the whole card links to the edit
             modal (?edit=…). No delete here — deletion lives in list view to
             prevent accidental removals. -->
        <div class="keeper-items-cardgrid">
          <?php foreach ($rows as $it): ?>
          <?php $icon = $it['icon_path'] ?? ($it['thumbnail'] ?? ''); ?>
          <a class="keeper-items-tile" href="/keeper/items.php?edit=<?= urlencode((string) $it['item_id']) ?>#add" title="<?= htmlspecialchars((string) $it['item_id']) ?>">
            <span class="keeper-items-tile__art">
              <?php if ($icon !== ''): ?>
                <img src="/<?= htmlspecialchars(ltrim((string) $icon, '/')) ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
              <?php else: ?>
                <span class="keeper-items-tile__noart"><?= htmlspecialchars(strtoupper(substr((string) $it['display_name'], 0, 1))) ?></span>
              <?php endif; ?>
            </span>
            <span class="keeper-items-tile__name"><?= htmlspecialchars((string) $it['display_name']) ?></span>
          </a>
          <?php endforeach; ?>
        </div>

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
                <th></th>
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
                <td class="keeper-items-desc keeper-cell-clamp keeper-cell-clamp--lg" title="<?= htmlspecialchars((string) ($it['description'] ?? '')) ?>"><?= htmlspecialchars((string) ($it['description'] ?? '')) ?></td>
                <td>
                  <div class="keeper-row-actions">
                    <a href="/keeper/items.php?edit=<?= urlencode((string) $it['item_id']) ?>#add" class="keeper-icon-btn" title="Edit item" aria-label="Edit">
                      <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/pen-to-square.svg" alt="">
                    </a>
                    <form method="post" action="/keeper/items.php" onsubmit="return confirm('Delete <?= htmlspecialchars((string) $it['item_id'], ENT_QUOTES) ?>?');">
                      <input type="hidden" name="keeper_csrf" value="<?= htmlspecialchars($keeperCsrf) ?>">
                      <input type="hidden" name="item_id" value="<?= htmlspecialchars((string) $it['item_id']) ?>">
                      <button type="submit" name="delete_item" value="1" class="keeper-icon-btn keeper-icon-btn--danger" title="Delete item" aria-label="Delete">
                        <img class="keeper-icon" src="https://nerd.biz/assets/fa/svgs/solid/trash.svg" alt="">
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
      </div><!-- /.keeper-items-catalog -->
    <?php endif; ?>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

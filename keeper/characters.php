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
 * Keeper > Characters — the authored roster of the game's characters (Humans,
 * NPCs, Zombies). Each character carries metadata + avatar/pose art and its own
 * talk-bubble lines (edited from a "Messages" button on its row). This roster is
 * destined to feed the game's runtime startup as the canonical character list.
 * All writes go to the HOST db via grave_db().
 *
 * The legacy game-synced npc_roster/npc_messages tables are retained in the DB
 * but no longer surfaced here (messages now key to characters.id via
 * character_messages, migration 015).
 */

/** Ensure the catalog + messages tables exist (idempotent; mirrors 014/015). */
function keeper_ensure_characters_table(PDO $db): void
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS characters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, age INTEGER, gender TEXT,
            type TEXT NOT NULL DEFAULT "Human", description TEXT,
            avatar_path TEXT, pose_path TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_characters_type   ON characters(type)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_characters_active ON characters(active)');
    // Wave-scaling dials (016) — added idempotently for DBs that predate it.
    foreach ([
        'hp_base' => 'INTEGER', 'wave_min' => 'INTEGER', 'wave_max' => 'INTEGER',
        'hp_cap' => 'INTEGER', 'hp_bands' => 'TEXT',
    ] as $col => $decl) {
        try { $db->exec("ALTER TABLE characters ADD COLUMN {$col} {$decl}"); }
        catch (Throwable $e) { /* already exists */ }
    }
    $db->exec(
        'CREATE TABLE IF NOT EXISTS character_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, character_id INTEGER NOT NULL,
            body TEXT NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, weight INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
        )'
    );
    $db->exec('CREATE INDEX IF NOT EXISTS idx_character_messages_char ON character_messages(character_id)');
}

/** Allowed character types (extensible later). */
const KEEPER_CHAR_TYPES = ['Human', 'NPC', 'Zombie', 'Enemy'];
/** Allowed gender codes. */
const KEEPER_CHAR_GENDERS = ['m', 'f', 'unknown'];

/**
 * Validate + store one uploaded character image under assets/characters/.
 * Returns the stored web path on success, '' if no file was uploaded, or throws
 * RuntimeException on a bad upload. MIME is sniffed server-side.
 */
function keeper_store_character_image(array $file, string $slug): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed (code ' . (int) $file['error'] . ').');
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Image too large (max 5 MB).');
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
    $dir = __DIR__ . '/../assets/characters';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Could not create the image directory.');
    }
    $name = $slug . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'assets/characters/' . $name;
}

/** URL-safe slug from a character name, for image filenames. */
function keeper_char_slug(string $name): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name)));
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? substr($slug, 0, 40) : 'character';
}

/** Optional non-negative int from a POST field: '' -> null, else max(0,int). */
function keeper_opt_int($v): ?int
{
    $v = trim((string) $v);
    return $v === '' ? null : max(0, (int) $v);
}

/**
 * Assemble the hp_bands JSON from the parallel POST arrays (band_from[],
 * band_to[], band_mode[], band_value[]). Rows with an empty "from" or "value"
 * are dropped (blank template rows). Returns a JSON string, or null when there
 * are no valid bands (so absent = no growth, matching the game's fallback).
 */
function keeper_bands_json(array $post): ?string
{
    $from  = (array) ($post['band_from'] ?? []);
    $to    = (array) ($post['band_to'] ?? []);
    $mode  = (array) ($post['band_mode'] ?? []);
    $value = (array) ($post['band_value'] ?? []);

    $bands = [];
    foreach ($from as $i => $f) {
        $f = trim((string) $f);
        $v = trim((string) ($value[$i] ?? ''));
        if ($f === '' || $v === '') {
            continue; // incomplete/blank row
        }
        $t = trim((string) ($to[$i] ?? ''));
        $m = ($mode[$i] ?? 'pct') === 'flat' ? 'flat' : 'pct';
        $bands[] = [
            'from'  => max(1, (int) $f),
            'to'    => $t === '' ? null : max(1, (int) $t),
            'mode'  => $m,
            'value' => 0 + $v, // numeric (int or float)
        ];
    }

    if (!$bands) {
        return null;
    }
    // Order by 'from' so the tiling reads correctly regardless of input order.
    usort($bands, fn ($a, $b) => $a['from'] <=> $b['from']);

    return json_encode($bands, JSON_UNESCAPED_SLASHES);
}

// Keeper-scoped CSRF token.
if (empty($_SESSION['keeper_csrf'])) {
    $_SESSION['keeper_csrf'] = bin2hex(random_bytes(32));
}
$keeperCsrf = $_SESSION['keeper_csrf'];

$db = grave_db();
keeper_ensure_characters_table($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['keeper_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($keeperCsrf, $token)) {
        $_SESSION['keeper_flash'] = 'Invalid request. Please try again.';
        header('Location: /keeper/characters.php');
        exit;
    }

    // --- Save (create or update) a catalog character ---
    if (isset($_POST['save_character'])) {
        $id     = (int) ($_POST['id'] ?? 0);
        $name   = trim((string) ($_POST['name'] ?? ''));
        $ageRaw = trim((string) ($_POST['age'] ?? ''));
        $age    = ($ageRaw === '') ? null : max(0, (int) $ageRaw);
        $gender = (string) ($_POST['gender'] ?? '');
        $type   = (string) ($_POST['type'] ?? 'Human');
        $desc   = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $_SESSION['keeper_flash'] = 'Character name is required.';
            header('Location: /keeper/characters.php');
            exit;
        }
        if (!in_array($gender, KEEPER_CHAR_GENDERS, true)) {
            $gender = 'unknown';
        }
        if (!in_array($type, KEEPER_CHAR_TYPES, true)) {
            $type = 'Human';
        }

        // Wave-scaling dials (all optional; absent = today's behavior).
        $hpBase  = keeper_opt_int($_POST['hp_base'] ?? '');
        $waveMin = keeper_opt_int($_POST['wave_min'] ?? '');
        $waveMax = keeper_opt_int($_POST['wave_max'] ?? '');
        $hpCap   = keeper_opt_int($_POST['hp_cap'] ?? '');
        $hpBands = keeper_bands_json($_POST);

        try {
            $slug      = keeper_char_slug($name);
            $avatarNew = keeper_store_character_image($_FILES['avatar'] ?? [], $slug . '-avatar');
            $poseNew   = keeper_store_character_image($_FILES['pose'] ?? [], $slug . '-pose');
        } catch (Throwable $e) {
            $_SESSION['keeper_flash'] = 'Image error: ' . $e->getMessage();
            header('Location: /keeper/characters.php');
            exit;
        }

        if ($id > 0) {
            $cur = $db->prepare('SELECT avatar_path, pose_path FROM characters WHERE id = ?');
            $cur->execute([$id]);
            $row = $cur->fetch() ?: ['avatar_path' => null, 'pose_path' => null];
            $avatar = $avatarNew !== '' ? $avatarNew : $row['avatar_path'];
            $pose   = $poseNew   !== '' ? $poseNew   : $row['pose_path'];

            $stmt = $db->prepare(
                'UPDATE characters SET name=:name, age=:age, gender=:gender, type=:type,
                        description=:description, avatar_path=:avatar, pose_path=:pose,
                        hp_base=:hp_base, wave_min=:wave_min, wave_max=:wave_max,
                        hp_cap=:hp_cap, hp_bands=:hp_bands,
                        updated_at=CURRENT_TIMESTAMP
                 WHERE id=:id'
            );
            $stmt->execute([
                ':name' => $name, ':age' => $age, ':gender' => $gender, ':type' => $type,
                ':description' => ($desc === '' ? null : $desc),
                ':avatar' => $avatar, ':pose' => $pose,
                ':hp_base' => $hpBase, ':wave_min' => $waveMin, ':wave_max' => $waveMax,
                ':hp_cap' => $hpCap, ':hp_bands' => $hpBands,
                ':id' => $id,
            ]);
            $_SESSION['keeper_flash'] = "Updated \"{$name}\".";
        } else {
            $stmt = $db->prepare(
                'INSERT INTO characters (name, age, gender, type, description, avatar_path, pose_path,
                        hp_base, wave_min, wave_max, hp_cap, hp_bands)
                 VALUES (:name, :age, :gender, :type, :description, :avatar, :pose,
                        :hp_base, :wave_min, :wave_max, :hp_cap, :hp_bands)'
            );
            $stmt->execute([
                ':name' => $name, ':age' => $age, ':gender' => $gender, ':type' => $type,
                ':description' => ($desc === '' ? null : $desc),
                ':avatar' => ($avatarNew === '' ? null : $avatarNew),
                ':pose'   => ($poseNew === '' ? null : $poseNew),
                ':hp_base' => $hpBase, ':wave_min' => $waveMin, ':wave_max' => $waveMax,
                ':hp_cap' => $hpCap, ':hp_bands' => $hpBands,
            ]);
            $_SESSION['keeper_flash'] = "Added \"{$name}\".";
        }

        header('Location: /keeper/characters.php');
        exit;
    }

    // --- Delete a catalog character (removes its image files + messages) ---
    if (isset($_POST['delete_character'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $cur = $db->prepare('SELECT name, avatar_path, pose_path FROM characters WHERE id = ?');
            $cur->execute([$id]);
            if ($row = $cur->fetch()) {
                foreach ([$row['avatar_path'], $row['pose_path']] as $p) {
                    if ($p) {
                        $abs = __DIR__ . '/../' . ltrim((string) $p, '/');
                        if (is_file($abs)) {
                            @unlink($abs);
                        }
                    }
                }
                $db->prepare('DELETE FROM characters WHERE id = ?')->execute([$id]); // cascades messages
                $_SESSION['keeper_flash'] = 'Character "' . $row['name'] . '" deleted.';
            }
        }
        header('Location: /keeper/characters.php');
        exit;
    }

    // --- Toggle a character's Active flag (included in the runtime feed) ---
    if (isset($_POST['toggle_active'])) {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('UPDATE characters SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
               ->execute([$id]);
        }
        header('Location: /keeper/characters.php');
        exit;
    }

    // --- Save talk-bubble lines for one character (from its Messages modal) ---
    if (isset($_POST['save_messages'])) {
        $cid = (int) ($_POST['character_id'] ?? 0);
        $exists = $db->prepare('SELECT name FROM characters WHERE id = ?');
        $exists->execute([$cid]);
        $charName = $exists->fetchColumn();
        if ($cid <= 0 || $charName === false) {
            $_SESSION['keeper_flash'] = 'Unknown character. Nothing was saved.';
            header('Location: /keeper/characters.php');
            exit;
        }

        $rows = $_POST['rows'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }

        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE character_messages SET body=?, enabled=?, updated_at=CURRENT_TIMESTAMP WHERE id=? AND character_id=?');
            $delete = $db->prepare('DELETE FROM character_messages WHERE id=? AND character_id=?');
            $insert = $db->prepare('INSERT INTO character_messages (character_id, body, enabled) VALUES (?, ?, ?)');

            foreach ($rows as $key => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $body    = trim((string) ($row['body'] ?? ''));
                $enabled = empty($row['enabled']) ? 0 : 1;

                if ($key === 'new') {
                    if ($body !== '') {
                        $insert->execute([$cid, $body, $enabled]);
                    }
                    continue;
                }
                $mid = (int) $key;
                if ($mid <= 0) {
                    continue;
                }
                if (!empty($row['delete']) || $body === '') {
                    $delete->execute([$mid, $cid]);
                    continue;
                }
                $update->execute([$body, $enabled, $mid, $cid]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            $_SESSION['keeper_flash'] = 'Save failed: ' . $e->getMessage();
            header('Location: /keeper/characters.php');
            exit;
        }

        $_SESSION['keeper_flash'] = "Saved lines for {$charName}.";
        header('Location: /keeper/characters.php');
        exit;
    }

    // Unknown POST — bounce back.
    header('Location: /keeper/characters.php');
    exit;
}

$pageTitle = 'Characters — Keeper';
$pageCss = ['/css/keeper-characters.css'];
$pageJs  = ['/js/keeper-characters.js'];
include __DIR__ . '/../partials/keeper-header.php';

$flash = $_SESSION['keeper_flash'] ?? null;
unset($_SESSION['keeper_flash']);

// Optional ?edit=<id> loads one character into the add/edit form.
$editCharId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editChar = null;
if ($editCharId > 0) {
    $stmt = $db->prepare('SELECT * FROM characters WHERE id = ?');
    $stmt->execute([$editCharId]);
    $editChar = $stmt->fetch() ?: null;
}

$catalog = $db->query('SELECT * FROM characters ORDER BY type, sort_order, name')->fetchAll();
$catalogByType = [];
foreach ($catalog as $c) {
    $catalogByType[$c['type']][] = $c;
}

// Preload all messages grouped by character id (one query, avoids N+1).
$messagesByChar = [];
foreach ($db->query('SELECT id, character_id, body, enabled FROM character_messages ORDER BY id')->fetchAll() as $m) {
    $messagesByChar[(int) $m['character_id']][] = $m;
}
?>

<main class="keeper-main">
  <div class="container">
    <h1 class="keeper-page-title">Characters</h1>

    <?php if ($flash): ?>
    <p class="keeper-flash"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <?php include __DIR__ . '/_characters-catalog.php'; ?>
  </div>
</main>

<?php include __DIR__ . '/../partials/keeper-footer.php'; ?>

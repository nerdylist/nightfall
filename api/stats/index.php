<?php
/**
 * THE DEAD LAST — API: player stats ingest + survivors (game server only).
 *
 * Auth: Authorization: Bearer <GAME_API_KEY>   (.env GAME_API_KEY)
 *
 * POST /api/stats  -> report stats and/or a survivor lifecycle event.
 *      body { "username": "<users.username>",
 *             "stats":     { "<column>": <int >= 0>, ... },          // optional
 *             "survivor": { ... },                                   // optional
 *             "survivor_id": <int> | "survivor_ref": "<string>" }   // optional stat context
 *      At least one of "stats" / "survivor" is required.
 *
 *      Stats semantics (docs/player-stats.md):
 *        counters  (humans_killed, zombies_killed, times_turned, deaths,
 *                   true_deaths, redemptions, playtime_seconds, lives)
 *                  -> incremented by the sent value (send DELTAS)
 *        maxes     (biggest_horde_size, longest_life_seconds)
 *                  -> max(stored, sent) — never lowered
 *        set       (bank) -> replaced by the sent value (game owns balance)
 *      Row is upserted (first report creates it); updated_at refreshed.
 *
 *      Survivor create: "survivor": { "ref": "<any string>", "skin": "...",
 *      "name": "..."? } — ref is an OPAQUE game-side identifier stored
 *      verbatim (never parsed/validated structurally). Creates the row AND
 *      increments player_stats.lives (a new survivor IS a new life).
 *      Returns the numeric survivor id + the ref.
 *
 *      Survivor end: "survivor": { "id": <int> | "ref": "<string>",
 *      "ended": true, "outcome": "..."? } — stamps ended_at (+ outcome).
 *
 *      Survivor progression (survivor-progression-handoff.md): the "survivor"
 *      block may carry the eight attributes (str, agi, end, int, awa, luk, foc,
 *      fai — ints 0..10, ABSOLUTE) and "points_spent" (absolute) — on create
 *      (initial sheet) or as a bare { id|ref, <attrs>, points_spent } update
 *      (a point spend). XP is a DELTA in "stats": { "xp": <int >= 0> } — added
 *      to the survivor's cumulative xp (monotonic; never decreases). All three
 *      require a survivor (context or a survivor block in the post). Validated:
 *      attrs 0..10, sum(8) <= 14 + points_spent, points_spent <= points the xp
 *      earns (curve cost(n)=4000+600(n-1)+90(n-1)^2). A failure 400s the whole
 *      post. The survivor echo includes the sheet + derived points_earned.
 *
 *      "survivor_id" / "survivor_ref" alongside "stats" is validated as
 *      belonging to the user and echoed back.
 *
 *      "daily_playtime": [{ "date": "YYYY-MM-DD", "seconds": <int >= 0> }, ...]
 *      (optional, PER-SURVIVOR) — absolute per-real-day active survival time.
 *      Max 40 buckets/post. UPSERTS each (survivor, date) with
 *      seconds = max(stored, sent) so resends are idempotent/monotonic. Needs
 *      a survivor: a "survivor" create/end in this post OR survivor context
 *      (survivor_id/survivor_ref). Applied buckets are echoed as
 *      "applied_playtime". Feeds the season/daily leaderboard (api/leaderboard).
 *
 * GET  /api/stats?username=<name>  -> the player's current stats row
 *      (all zeros if the player has never reported).
 *
 * Errors (JSON): 401 bad/missing bearer, 404 unknown username / survivor,
 * 400 malformed payload / unknown stat key / non-integer or negative value,
 * 405 other methods. See docs/game-stats-api.md for the full contract.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

/** Stat columns the game may report, with their ingest semantics. */
const STATS_COLUMNS = [
    'humans_killed'        => 'counter',
    'zombies_killed'       => 'counter',
    'times_turned'         => 'counter',
    'deaths'               => 'counter',
    'true_deaths'          => 'counter',
    'redemptions'          => 'counter',
    'biggest_horde_size'   => 'max',
    'longest_life_seconds' => 'max',
    'playtime_seconds'     => 'counter',
    'bank'                 => 'set',
    'lives'                => 'counter',

    // Season boards (011, 2026-07-22 — leaderboard-boards-spec.md).
    'kills_hvz'               => 'counter',
    'kills_hvh'               => 'counter',
    'kills_zvz'               => 'counter',
    'kills_zvh'               => 'counter',
    'bat_kills'               => 'counter',
    'humans_infected'         => 'counter',
    'chests_looted'           => 'counter',
    'distance_m'              => 'counter',
    'banked_total'            => 'counter',
    'hunter_pure_kills'       => 'max',
    'allie_pure_kills'        => 'max',
    'died_rich'               => 'max',
    'insomniac_seconds'       => 'max',
    'long_walk_seconds'       => 'max',
    'kill_free_life_seconds'  => 'max',
    'lazarus_seconds'         => 'min',
    'fastest_death_seconds'   => 'min',
];

/**
 * Survivor progression attributes (survivor-progression-handoff.md §2). Order
 * matters — the game sends them in this order. All ints 0..10. Stored on the
 * survivor row (not player_stats). `int`/`end` are SQLite-ish keywords so every
 * query quotes the column names.
 */
const SURVIVOR_ATTRS = ['str', 'agi', 'end', 'int', 'awa', 'luk', 'foc', 'fai'];
/** Starting allocation pool (base 0 at create; buys the first 14 points free). */
const SURVIVOR_START_POOL = 14;
/** Per-attribute cap from allocation. */
const SURVIVOR_ATTR_CAP = 10;

/**
 * XP cost to buy the nth stat point, via the game's fixed curve (refit for the
 * eight-stat / 66-point ladder, 2026-07-29):
 *   cost(n) = 4000 + 600·k + 32.3·k²   where k = n-1, rounded to nearest 100.
 * Matched to the game's exact integer implementation so the two can't disagree
 * at a rounding boundary and reject a legitimate spend:
 *   - the quadratic term is (323·k·k)/10 with integer division (floor)
 *   - the whole cost is rounded HALF-UP to the nearest 100
 * Full 66-point ladder ≈ 4,576,400 XP. (Validation only, not stored — if the
 * game retunes the curve, this one function changes, no migration.)
 */
function survivor_point_cost(int $n): int
{
    $k = $n - 1;
    $raw = 4000 + 600 * $k + intdiv(323 * $k * $k, 10);
    return intdiv($raw + 50, 100) * 100; // round half-up to nearest 100
}

/**
 * Points earned from cumulative XP: how many whole points `xp` can afford
 * (cumulative). Used to validate points_spent <= points_earned.
 */
function survivor_points_earned(int $xp): int
{
    if ($xp <= 0) {
        return 0;
    }
    $earned = 0;
    $spent = 0;
    for ($n = 1; ; $n++) {
        $cost = survivor_point_cost($n);
        if ($spent + $cost > $xp) {
            break;
        }
        $spent += $cost;
        $earned = $n;
        if ($n > 100000) { // hard stop, far beyond max (66)
            break;
        }
    }
    return $earned;
}

/**
 * Extract survivor progression fields from a `survivor` block. Returns
 * ['attrs' => [name=>int,...present only], 'points_spent' => int|null]. Values
 * are read as-is (validation happens later against the resolved row).
 */
function survivor_progression_from_block(array $block): array
{
    $attrs = [];
    foreach (SURVIVOR_ATTRS as $a) {
        if (array_key_exists($a, $block)) {
            $attrs[$a] = $block[$a];
        }
    }
    $points = array_key_exists('points_spent', $block) ? $block['points_spent'] : null;
    return ['attrs' => $attrs, 'points_spent' => $points];
}

/**
 * Verify the Authorization: Bearer header against .env GAME_API_KEY.
 * (Same pattern as meshy_verify_bearer in lib/meshy.php.)
 */
function stats_verify_bearer(): bool
{
    $secret = trim((string) env('GAME_API_KEY', ''));
    if ($secret === '') {
        return false;
    }

    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    // Some FastCGI setups expose it under a redirect-prefixed name.
    if ($auth === '' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $auth = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    if (stripos($auth, 'Bearer ') === 0) {
        $token = trim(substr($auth, 7));
        return $token !== '' && hash_equals($secret, $token);
    }

    return false;
}

/** Resolve a username to users.id, or null if unknown. */
function stats_user_id(PDO $pdo, string $username): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $id = $stmt->fetchColumn();

    return ($id === false) ? null : (int) $id;
}

/** Fetch the full stats row for a user (all zeros if never reported). */
function stats_row(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT * FROM player_stats WHERE user_id = :user_id');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();

    if ($row === false) {
        $row = ['user_id' => $userId, 'updated_at' => null];
        foreach (array_keys(STATS_COLUMNS) as $column) {
            $row[$column] = 0;
        }
        return $row;
    }

    foreach (array_keys(STATS_COLUMNS) as $column) {
        $row[$column] = (int) $row[$column];
    }
    $row['user_id'] = (int) $row['user_id'];

    return $row;
}

/**
 * Upsert validated stat values onto the user's player_stats row.
 * Column names come from the STATS_COLUMNS whitelist only; values are
 * always bound through prepared-statement placeholders.
 * NOTE: max columns use CASE WHEN instead of scalar max() — inside an
 * upsert's DO UPDATE, max(player_stats.col, excluded.col) resolves both
 * arguments to the excluded value on the SQLite build bundled with PHP.
 */
function stats_apply(PDO $pdo, int $userId, array $clean): void
{
    $columns = array_keys($clean);
    $insertCols = implode(', ', $columns);
    $placeholders = implode(', ', array_map(fn ($c) => ':' . $c, $columns));

    $updates = [];
    foreach ($columns as $column) {
        $updates[] = match (STATS_COLUMNS[$column]) {
            'counter' => "{$column} = player_stats.{$column} + excluded.{$column}",
            'max'     => "{$column} = CASE WHEN excluded.{$column} > player_stats.{$column}"
                       . " THEN excluded.{$column} ELSE player_stats.{$column} END",
            // min: 0 means "never set" — first real value wins, then only lower.
            'min'     => "{$column} = CASE WHEN excluded.{$column} > 0 AND"
                       . " (player_stats.{$column} = 0 OR excluded.{$column} < player_stats.{$column})"
                       . " THEN excluded.{$column} ELSE player_stats.{$column} END",
            'set'     => "{$column} = excluded.{$column}",
        };
    }
    $updates[] = 'updated_at = CURRENT_TIMESTAMP';

    $sql = "INSERT INTO player_stats (user_id, {$insertCols}) VALUES (:user_id, {$placeholders})
            ON CONFLICT(user_id) DO UPDATE SET " . implode(', ', $updates);

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(['user_id' => $userId], $clean));
}

/**
 * SURVIVOR VIEW (012, Boss 2026-07-23 "SURVIVOR | USER | ALL"): mirror the
 * same validated stat values onto the survivor's own survivor_stats row —
 * identical semantics, keyed by survivor_id. Called only when the post
 * carries a survivor context; 'lives' is user-level and skipped.
 */
function stats_apply_survivor(PDO $pdo, int $survivorId, array $clean): void
{
    unset($clean['lives']);
    if ($clean === []) {
        return;
    }

    $columns = array_keys($clean);
    $insertCols = implode(', ', $columns);
    $placeholders = implode(', ', array_map(fn ($c) => ':' . $c, $columns));

    $updates = [];
    foreach ($columns as $column) {
        $updates[] = match (STATS_COLUMNS[$column]) {
            'counter' => "{$column} = survivor_stats.{$column} + excluded.{$column}",
            'max'     => "{$column} = CASE WHEN excluded.{$column} > survivor_stats.{$column}"
                       . " THEN excluded.{$column} ELSE survivor_stats.{$column} END",
            'min'     => "{$column} = CASE WHEN excluded.{$column} > 0 AND"
                       . " (survivor_stats.{$column} = 0 OR excluded.{$column} < survivor_stats.{$column})"
                       . " THEN excluded.{$column} ELSE survivor_stats.{$column} END",
            'set'     => "{$column} = excluded.{$column}",
        };
    }

    $sql = "INSERT INTO survivor_stats (survivor_id, {$insertCols}) VALUES (:survivor_id, {$placeholders})
            ON CONFLICT(survivor_id) DO UPDATE SET " . implode(', ', $updates);

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(['survivor_id' => $survivorId], $clean));
}

/** Public shape of a survivor row for API responses. */
function stats_survivor_public(array $row): array
{
    $out = [
        'id'         => (int) $row['id'],
        'ref'        => (string) $row['ref'],
        'name'       => $row['name'],
        'skin'       => (string) $row['skin'],
        'started_at' => $row['started_at'],
        'ended_at'   => $row['ended_at'],
        'outcome'    => $row['outcome'],
    ];
    // Progression (022) — present when the row has the columns. Echo the sheet
    // back so the game can reconcile against the authoritative copy.
    if (array_key_exists('xp', $row)) {
        foreach (SURVIVOR_ATTRS as $a) {
            $out[$a] = (int) ($row[$a] ?? 0);
        }
        $out['xp'] = (int) $row['xp'];
        $out['points_spent'] = (int) ($row['points_spent'] ?? 0);
        $out['points_earned'] = survivor_points_earned((int) $row['xp']); // derived, for convenience
    }
    return $out;
}

/**
 * Find a user's survivor by numeric id or by exact-string ref (opaque —
 * matched verbatim, newest row wins if the game reused a ref). Null if the
 * user has no matching survivor.
 */
function stats_find_survivor(PDO $pdo, int $userId, ?int $id, ?string $ref): ?array
{
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM survivors WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM survivors WHERE user_id = :user_id AND ref = :ref
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'ref' => (string) $ref]);
    }

    $row = $stmt->fetch();

    return ($row === false) ? null : $row;
}

/** Max number of daily_playtime buckets accepted in a single post. */
const PLAYTIME_MAX_BUCKETS = 40;

/** True when $value is a real calendar date in YYYY-MM-DD form. */
function stats_valid_date(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

/**
 * Upsert per-survivor daily playtime buckets. The game sends ABSOLUTE
 * per-day totals, so each (survivor, date) is max-merged — resends are
 * idempotent and can only move a bucket up (never down). $buckets is the
 * pre-validated list of ['date' => 'YYYY-MM-DD', 'seconds' => <int >= 0>].
 */
function stats_apply_playtime(PDO $pdo, int $survivorId, array $buckets): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO survivor_playtime (survivor_id, date, seconds)
         VALUES (:survivor_id, :date, :seconds)
         ON CONFLICT(survivor_id, date) DO UPDATE SET
           seconds = CASE WHEN excluded.seconds > survivor_playtime.seconds
                          THEN excluded.seconds ELSE survivor_playtime.seconds END'
    );

    foreach ($buckets as $bucket) {
        $stmt->execute([
            'survivor_id' => $survivorId,
            'date'         => $bucket['date'],
            'seconds'      => $bucket['seconds'],
        ]);
    }
}

if (!stats_verify_bearer()) {
    grave_json_response(401, ['success' => false, 'error' => 'Unauthorized.']);
}

$pdo = grave_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $username = isset($_GET['username']) ? trim((string) $_GET['username']) : '';
    if ($username === '') {
        grave_json_response(400, ['success' => false, 'error' => 'Missing required "username" parameter.']);
    }

    $userId = stats_user_id($pdo, $username);
    if ($userId === null) {
        grave_json_response(404, ['success' => false, 'error' => 'Unknown username.']);
    }

    grave_json_response(200, [
        'success'  => true,
        'username' => $username,
        'stats'    => stats_row($pdo, $userId),
    ]);
}

if ($method !== 'POST') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

$input = grave_read_json_input();

$username = isset($input['username']) ? trim((string) $input['username']) : '';
if ($username === '') {
    grave_json_response(400, ['success' => false, 'error' => 'Missing required "username" field.']);
}

$stats = $input['stats'] ?? null;
$survivor = $input["survivor"] ?? null;
$hasPlaytime = array_key_exists('daily_playtime', $input);

if (($stats === null || $stats === []) && !is_array($survivor) && !$hasPlaytime) {
    grave_json_response(400, ['success' => false, 'error' => 'Request must include "stats", "survivor", and/or "daily_playtime".']);
}

// ---- Pull survivor XP out of the stats block (it lives on the SURVIVOR row,
//      not player_stats). Sent as a non-negative DELTA like kills/playtime.
//      Removed here so it isn't rejected as an "unknown stat key" below. ----
$xpDelta = null;
if (is_array($stats) && array_key_exists('xp', $stats)) {
    $xpRaw = $stats['xp'];
    $isIntegral = is_numeric($xpRaw) && !is_bool($xpRaw) && (float) $xpRaw == (int) $xpRaw;
    if (!$isIntegral || (int) $xpRaw < 0) {
        grave_json_response(400, ['success' => false, 'error' => '"xp" must be a non-negative integer (a cumulative delta).']);
    }
    $xpDelta = (int) $xpRaw;
    unset($stats['xp']);
}

// ---- Validate the stats object (before touching the database). ----
$clean = [];
if (is_array($stats) && $stats !== []) {
    foreach ($stats as $key => $value) {
        if (!array_key_exists($key, STATS_COLUMNS)) {
            grave_json_response(400, [
                'success'    => false,
                'error'      => 'Unknown stat key: ' . (string) $key,
                'valid_keys' => array_keys(STATS_COLUMNS),
            ]);
        }

        // Accept ints (and integral strings/floats from lenient serializers);
        // reject non-integral values and any negative value.
        $isIntegral = is_numeric($value) && !is_bool($value) && (float) $value == (int) $value;
        if (!$isIntegral || (int) $value < 0) {
            grave_json_response(400, [
                'success' => false,
                'error'   => 'Stat "' . $key . '" must be a non-negative integer.',
            ]);
        }

        $clean[$key] = (int) $value;
    }
}

// ---- Validate the survivor action. ----
// Create: { ref, skin, name? }   End: { id|ref, ended: true, outcome? }
$survivorAction = null; // null | 'create' | 'end' | 'update'
$survRef = null;
$survId = null;
$survSkin = null;
$survName = null;
$survOutcome = null;
$survProgression = ['attrs' => [], 'points_spent' => null]; // attrs/points from the block

if ($survivor !== null) {
    if (!is_array($survivor)) {
        grave_json_response(400, ['success' => false, 'error' => '"survivor" must be an object.']);
    }

    // Progression (8 attrs + points_spent) can ride any survivor block —
    // create sets the initial sheet, a bare {id, str, points_spent} is a spend.
    $survProgression = survivor_progression_from_block($survivor);
    $hasProgression = $survProgression['attrs'] !== [] || $survProgression['points_spent'] !== null;

    if (!empty($survivor['ended'])) {
        $survivorAction = 'end';

        if (isset($survivor['id'])) {
            if (!is_numeric($survivor['id']) || (int) $survivor['id'] <= 0) {
                grave_json_response(400, ['success' => false, 'error' => 'Survivor "id" must be a positive integer.']);
            }
            $survId = (int) $survivor['id'];
        } elseif (isset($survivor['ref']) && trim((string) $survivor['ref']) !== '') {
            $survRef = (string) $survivor['ref'];
        } else {
            grave_json_response(400, ['success' => false, 'error' => 'Ending a survivor requires "id" or "ref".']);
        }

        if (isset($survivor['outcome']) && trim((string) $survivor['outcome']) !== '') {
            $survOutcome = trim((string) $survivor['outcome']);
        }
    } elseif (isset($survivor['skin'])) {
        // A "skin" means create (ref+skin required). Progression rides along.
        $survivorAction = 'create';

        $survRef = isset($survivor['ref']) ? (string) $survivor['ref'] : '';
        $survSkin = trim((string) $survivor['skin']);
        if ($survRef === '') {
            grave_json_response(400, ['success' => false, 'error' => 'Survivor create requires a non-empty "ref" string.']);
        }
        if ($survSkin === '') {
            grave_json_response(400, ['success' => false, 'error' => 'Survivor create requires a non-empty "skin".']);
        }
        if (isset($survivor['name']) && trim((string) $survivor['name']) !== '') {
            $survName = trim((string) $survivor['name']);
        }
    } elseif ((isset($survivor['id']) || isset($survivor['ref'])) && $hasProgression) {
        // Point-spend / attribute update: {id|ref, <attrs>, points_spent}.
        $survivorAction = 'update';
        if (isset($survivor['id'])) {
            if (!is_numeric($survivor['id']) || (int) $survivor['id'] <= 0) {
                grave_json_response(400, ['success' => false, 'error' => 'Survivor "id" must be a positive integer.']);
            }
            $survId = (int) $survivor['id'];
        } else {
            $survRef = (string) $survivor['ref'];
            if (trim($survRef) === '') {
                grave_json_response(400, ['success' => false, 'error' => 'Survivor update requires "id" or a non-empty "ref".']);
            }
        }
    } else {
        grave_json_response(400, [
            'success' => false,
            'error'   => 'Unrecognized "survivor" action: send {ref, skin} to create, {id|ref, ended:true} to end, or {id|ref, <attrs>/points_spent} to update.',
        ]);
    }
}

// ---- Optional survivor context on a stats report (no aggregation yet). ----
$contextId = null;
$contextRef = null;
if (isset($input['survivor_id'])) {
    if (!is_numeric($input['survivor_id']) || (int) $input['survivor_id'] <= 0) {
        grave_json_response(400, ['success' => false, 'error' => '"survivor_id" must be a positive integer.']);
    }
    $contextId = (int) $input['survivor_id'];
} elseif (isset($input['survivor_ref'])) {
    if (trim((string) $input['survivor_ref']) === '') {
        grave_json_response(400, ['success' => false, 'error' => '"survivor_ref" must be a non-empty string.']);
    }
    $contextRef = (string) $input['survivor_ref'];
}

// ---- Validate daily playtime buckets (optional, per-survivor). ----
// [{ "date": "YYYY-MM-DD", "seconds": <int >= 0> }, ...]. The game sends
// absolute per-day totals; ingest max-merges each (survivor, date). Requires
// a survivor to attach to: a create/end in this post, or survivor context.
$cleanPlaytime = [];
if (array_key_exists('daily_playtime', $input)) {
    $daily = $input['daily_playtime'];
    if (!is_array($daily)) {
        grave_json_response(400, ['success' => false, 'error' => '"daily_playtime" must be an array of {date, seconds}.']);
    }
    if (count($daily) > PLAYTIME_MAX_BUCKETS) {
        grave_json_response(400, [
            'success' => false,
            'error'   => 'Too many "daily_playtime" buckets (max ' . PLAYTIME_MAX_BUCKETS . ').',
        ]);
    }

    // Collapse duplicate dates within one post by max, so a payload can't
    // fight itself; the DB conflict resolution then max-merges against stored.
    foreach ($daily as $bucket) {
        if (!is_array($bucket) || !isset($bucket['date'], $bucket['seconds'])) {
            grave_json_response(400, ['success' => false, 'error' => 'Each "daily_playtime" entry needs "date" and "seconds".']);
        }

        $date = trim((string) $bucket['date']);
        if (!stats_valid_date($date)) {
            grave_json_response(400, ['success' => false, 'error' => 'Invalid "daily_playtime" date (expected YYYY-MM-DD): ' . $date]);
        }

        $secondsRaw = $bucket['seconds'];
        $isIntegral = is_numeric($secondsRaw) && !is_bool($secondsRaw) && (float) $secondsRaw == (int) $secondsRaw;
        if (!$isIntegral || (int) $secondsRaw < 0) {
            grave_json_response(400, ['success' => false, 'error' => 'Playtime "seconds" for ' . $date . ' must be a non-negative integer.']);
        }

        $seconds = (int) $secondsRaw;
        if (!isset($cleanPlaytime[$date]) || $seconds > $cleanPlaytime[$date]) {
            $cleanPlaytime[$date] = $seconds;
        }
    }
}

// ---- Resolve the user, then write. ----
$userId = stats_user_id($pdo, $username);
if ($userId === null) {
    grave_json_response(404, ['success' => false, 'error' => 'Unknown username.']);
}

$contextSurvivor = null;
if ($contextId !== null || $contextRef !== null) {
    $row = stats_find_survivor($pdo, $userId, $contextId, $contextRef);
    if ($row === null) {
        grave_json_response(404, ['success' => false, 'error' => 'Unknown survivor for this user.']);
    }
    $contextSurvivor = stats_survivor_public($row);
}

// daily_playtime is per-survivor — it needs a survivor to attach to: either
// the survivor context on this post, or a survivor create/end in this post.
// (A create resolves its id inside the transaction below.)
if ($cleanPlaytime !== [] && $contextSurvivor === null && $survivorAction === null) {
    grave_json_response(400, [
        'success' => false,
        'error'   => '"daily_playtime" requires a survivor: send "survivor_id"/"survivor_ref", or a "survivor" create/end in the same post.',
    ]);
}

// XP and attribute changes are per-survivor too. An xp delta with no survivor
// context (no survivor_id/ref and no create/update/end in this post) is
// ambiguous — reject it so XP is never applied to the wrong (or no) survivor.
$hasProgressionWrite = ($survProgression['attrs'] !== []) || ($survProgression['points_spent'] !== null);
if (($xpDelta !== null || $hasProgressionWrite)
    && $contextSurvivor === null && $survivorAction === null) {
    grave_json_response(400, [
        'success' => false,
        'error'   => '"xp"/attributes require a survivor: send "survivor_id"/"survivor_ref", or a "survivor" create/update/end in the same post.',
    ]);
}

$survivorOut = null;

$pdo->beginTransaction();
try {
    if ($survivorAction === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO survivors (user_id, ref, name, skin) VALUES (:user_id, :ref, :name, :skin)'
        );
        $stmt->execute(['user_id' => $userId, 'ref' => $survRef, 'name' => $survName, 'skin' => $survSkin]);
        $newId = (int) $pdo->lastInsertId();

        // A new survivor is a new life.
        stats_apply($pdo, $userId, ['lives' => 1]);

        $survivorOut = stats_find_survivor($pdo, $userId, $newId, null);
    } elseif ($survivorAction === 'end') {
        $row = stats_find_survivor($pdo, $userId, $survId, $survRef);
        if ($row === null) {
            $pdo->rollBack();
            grave_json_response(404, ['success' => false, 'error' => 'Unknown survivor for this user.']);
        }

        $stmt = $pdo->prepare(
            'UPDATE survivors
             SET ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 outcome  = COALESCE(:outcome, outcome)
             WHERE id = :id'
        );
        $stmt->execute(['outcome' => $survOutcome, 'id' => (int) $row['id']]);

        $survivorOut = stats_find_survivor($pdo, $userId, (int) $row['id'], null);
    } elseif ($survivorAction === 'update') {
        // Point-spend / attribute change — resolve the target row; no other
        // side effects (progression is applied in the shared block below).
        $row = stats_find_survivor($pdo, $userId, $survId, $survRef);
        if ($row === null) {
            $pdo->rollBack();
            grave_json_response(404, ['success' => false, 'error' => 'Unknown survivor for this user.']);
        }
        $survivorOut = stats_find_survivor($pdo, $userId, (int) $row['id'], null);
    }

    // ---- Apply survivor progression (attrs / points_spent / xp) ----
    // Resolve the survivor this post targets: an explicit context, else the one
    // created/ended/updated in this same post.
    $progTargetId = null;
    if ($contextSurvivor !== null) {
        $progTargetId = (int) $contextSurvivor['id'];
    } elseif ($survivorOut !== null && isset($survivorOut['id'])) {
        $progTargetId = (int) $survivorOut['id'];
    }

    if ($progTargetId !== null && ($xpDelta !== null || $hasProgressionWrite || $survivorAction === 'create')) {
        // Read the current authoritative row (locked in this transaction).
        $curStmt = $pdo->prepare('SELECT * FROM survivors WHERE id = ? AND user_id = ?');
        $curStmt->execute([$progTargetId, $userId]);
        $cur = $curStmt->fetch();
        if ($cur === false) {
            $pdo->rollBack();
            grave_json_response(404, ['success' => false, 'error' => 'Unknown survivor for this user.']);
        }

        // XP is a monotonic cumulative delta; new total must never decrease.
        $newXp = (int) $cur['xp'];
        if ($xpDelta !== null) {
            $newXp = (int) $cur['xp'] + $xpDelta; // delta is non-negative (validated)
        }

        // Resolve the eight attribute values: sent values (absolute) win, else
        // keep stored. Validate each 0..cap.
        $attrs = [];
        foreach (SURVIVOR_ATTRS as $a) {
            if (array_key_exists($a, $survProgression['attrs'])) {
                $v = $survProgression['attrs'][$a];
                $isIntegral = is_numeric($v) && !is_bool($v) && (float) $v == (int) $v;
                if (!$isIntegral || (int) $v < 0 || (int) $v > SURVIVOR_ATTR_CAP) {
                    $pdo->rollBack();
                    grave_json_response(400, ['success' => false,
                        'error' => 'Attribute "' . $a . '" must be an integer 0..' . SURVIVOR_ATTR_CAP . '.']);
                }
                $attrs[$a] = (int) $v;
            } else {
                $attrs[$a] = (int) $cur[$a];
            }
        }

        // points_spent: sent (absolute) wins, else keep stored. Must be >= 0.
        $pointsSpent = (int) $cur['points_spent'];
        if ($survProgression['points_spent'] !== null) {
            $ps = $survProgression['points_spent'];
            $isIntegral = is_numeric($ps) && !is_bool($ps) && (float) $ps == (int) $ps;
            if (!$isIntegral || (int) $ps < 0) {
                $pdo->rollBack();
                grave_json_response(400, ['success' => false, 'error' => '"points_spent" must be a non-negative integer.']);
            }
            $pointsSpent = (int) $ps;
        }

        // Rule 1: sum of the eight attributes <= starting pool + points_spent.
        $attrSum = array_sum($attrs);
        if ($attrSum > SURVIVOR_START_POOL + $pointsSpent) {
            $pdo->rollBack();
            grave_json_response(400, ['success' => false,
                'error' => 'Attribute sum (' . $attrSum . ') exceeds allowed (' . (SURVIVOR_START_POOL + $pointsSpent) . ' = ' . SURVIVOR_START_POOL . ' start + ' . $pointsSpent . ' spent).']);
        }

        // Rule 2: points_spent <= points the survivor's XP actually earns.
        $earned = survivor_points_earned($newXp);
        if ($pointsSpent > $earned) {
            $pdo->rollBack();
            grave_json_response(400, ['success' => false,
                'error' => 'points_spent (' . $pointsSpent . ') exceeds points earned by XP (' . $earned . ' at ' . $newXp . ' XP).']);
        }

        // Persist. Column names str/agi/end/int/... — quote the reserved-ish ones.
        $sql = 'UPDATE survivors SET '
             . '"str"=:str, "agi"=:agi, "end"=:end, "int"=:int, "awa"=:awa, "luk"=:luk, "foc"=:foc, "fai"=:fai, '
             . 'xp=:xp, points_spent=:points_spent WHERE id=:id';
        $up = $pdo->prepare($sql);
        $up->execute(array_merge($attrs, [
            'xp' => $newXp, 'points_spent' => $pointsSpent, 'id' => $progTargetId,
        ]));

        // Refresh both the primary survivor echo AND the context echo (if this
        // post used one) so the response reflects the post-write sheet, not the
        // stale pre-transaction snapshot.
        $freshRow = stats_find_survivor($pdo, $userId, $progTargetId, null);
        $survivorOut = $freshRow;
        if ($contextSurvivor !== null && (int) $contextSurvivor['id'] === $progTargetId) {
            $contextSurvivor = stats_survivor_public($freshRow);
        }
    }

    if ($clean !== []) {
        stats_apply($pdo, $userId, $clean);

        // SURVIVOR VIEW (012): same values onto the survivor's own row when
        // this post has a survivor context (or created/ended one).
        $statsSurvId = null;
        if ($contextSurvivor !== null) {
            $statsSurvId = (int) $contextSurvivor['id'];
        } elseif ($survivorOut !== null && isset($survivorOut['id'])) {
            $statsSurvId = (int) $survivorOut['id'];
        }

        if ($statsSurvId !== null && $statsSurvId > 0) {
            stats_apply_survivor($pdo, $statsSurvId, $clean);
        }
    }

    if ($cleanPlaytime !== []) {
        // Attach to the context survivor if given, else the survivor
        // created/ended in this same post. The pre-transaction guard
        // guarantees one of these exists.
        $playtimeSurvId = ($contextSurvivor !== null)
            ? (int) $contextSurvivor['id']
            : (int) $survivorOut['id'];

        $buckets = [];
        foreach ($cleanPlaytime as $date => $seconds) {
            $buckets[] = ['date' => $date, 'seconds' => $seconds];
        }
        stats_apply_playtime($pdo, $playtimeSurvId, $buckets);
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    grave_json_response(500, ['success' => false, 'error' => 'Write failed.']);
}

$response = [
    'success'  => true,
    'username' => $username,
];
if ($survivorOut !== null) {
    $response['survivor'] = stats_survivor_public($survivorOut);
    if ($survivorAction === 'create') {
        $response['survivor_id'] = $response['survivor']['id'];
    }
}
if ($contextSurvivor !== null) {
    $response['survivor_context'] = $contextSurvivor;
}
if ($clean !== []) {
    $response['applied'] = $clean;
}
if ($cleanPlaytime !== []) {
    // Echo the buckets as applied (date-sorted), so the game can reconcile.
    ksort($cleanPlaytime);
    $applied = [];
    foreach ($cleanPlaytime as $date => $seconds) {
        $applied[] = ['date' => $date, 'seconds' => $seconds];
    }
    $response['applied_playtime'] = $applied;
}
$response['stats'] = stats_row($pdo, $userId);

grave_json_response(200, $response);

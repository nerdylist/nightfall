<?php
/**
 * THE DEAD LAST — API: player stats ingest + characters (game server only).
 *
 * Auth: Authorization: Bearer <GAME_API_KEY>   (.env GAME_API_KEY)
 *
 * POST /api/stats  -> report stats and/or a character lifecycle event.
 *      body { "username": "<users.username>",
 *             "stats":     { "<column>": <int >= 0>, ... },          // optional
 *             "character": { ... },                                   // optional
 *             "character_id": <int> | "character_ref": "<string>" }   // optional stat context
 *      At least one of "stats" / "character" is required.
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
 *      Character create: "character": { "ref": "<any string>", "skin": "...",
 *      "name": "..."? } — ref is an OPAQUE game-side identifier stored
 *      verbatim (never parsed/validated structurally). Creates the row AND
 *      increments player_stats.lives (a new character IS a new life).
 *      Returns the numeric character id + the ref.
 *
 *      Character end: "character": { "id": <int> | "ref": "<string>",
 *      "ended": true, "outcome": "..."? } — stamps ended_at (+ outcome).
 *
 *      "character_id" / "character_ref" alongside "stats" is validated as
 *      belonging to the user and echoed back; per-character stat
 *      aggregation is not implemented yet.
 *
 *      "daily_playtime": [{ "date": "YYYY-MM-DD", "seconds": <int >= 0> }, ...]
 *      (optional, PER-CHARACTER) — absolute per-real-day active survival time.
 *      Max 40 buckets/post. UPSERTS each (character, date) with
 *      seconds = max(stored, sent) so resends are idempotent/monotonic. Needs
 *      a character: a "character" create/end in this post OR character context
 *      (character_id/character_ref). Applied buckets are echoed as
 *      "applied_playtime". Feeds the season/daily leaderboard (api/leaderboard).
 *
 * GET  /api/stats?username=<name>  -> the player's current stats row
 *      (all zeros if the player has never reported).
 *
 * Errors (JSON): 401 bad/missing bearer, 404 unknown username / character,
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
 * same validated stat values onto the character's own character_stats row —
 * identical semantics, keyed by character_id. Called only when the post
 * carries a character context; 'lives' is user-level and skipped.
 */
function stats_apply_character(PDO $pdo, int $characterId, array $clean): void
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
            'counter' => "{$column} = character_stats.{$column} + excluded.{$column}",
            'max'     => "{$column} = CASE WHEN excluded.{$column} > character_stats.{$column}"
                       . " THEN excluded.{$column} ELSE character_stats.{$column} END",
            'min'     => "{$column} = CASE WHEN excluded.{$column} > 0 AND"
                       . " (character_stats.{$column} = 0 OR excluded.{$column} < character_stats.{$column})"
                       . " THEN excluded.{$column} ELSE character_stats.{$column} END",
            'set'     => "{$column} = excluded.{$column}",
        };
    }

    $sql = "INSERT INTO character_stats (character_id, {$insertCols}) VALUES (:character_id, {$placeholders})
            ON CONFLICT(character_id) DO UPDATE SET " . implode(', ', $updates);

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(['character_id' => $characterId], $clean));
}

/** Public shape of a character row for API responses. */
function stats_character_public(array $row): array
{
    return [
        'id'         => (int) $row['id'],
        'ref'        => (string) $row['ref'],
        'name'       => $row['name'],
        'skin'       => (string) $row['skin'],
        'started_at' => $row['started_at'],
        'ended_at'   => $row['ended_at'],
        'outcome'    => $row['outcome'],
    ];
}

/**
 * Find a user's character by numeric id or by exact-string ref (opaque —
 * matched verbatim, newest row wins if the game reused a ref). Null if the
 * user has no matching character.
 */
function stats_find_character(PDO $pdo, int $userId, ?int $id, ?string $ref): ?array
{
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM characters WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM characters WHERE user_id = :user_id AND ref = :ref
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
 * Upsert per-character daily playtime buckets. The game sends ABSOLUTE
 * per-day totals, so each (character, date) is max-merged — resends are
 * idempotent and can only move a bucket up (never down). $buckets is the
 * pre-validated list of ['date' => 'YYYY-MM-DD', 'seconds' => <int >= 0>].
 */
function stats_apply_playtime(PDO $pdo, int $characterId, array $buckets): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO character_playtime (character_id, date, seconds)
         VALUES (:character_id, :date, :seconds)
         ON CONFLICT(character_id, date) DO UPDATE SET
           seconds = CASE WHEN excluded.seconds > character_playtime.seconds
                          THEN excluded.seconds ELSE character_playtime.seconds END'
    );

    foreach ($buckets as $bucket) {
        $stmt->execute([
            'character_id' => $characterId,
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
$character = $input['character'] ?? null;
$hasPlaytime = array_key_exists('daily_playtime', $input);

if (($stats === null || $stats === []) && !is_array($character) && !$hasPlaytime) {
    grave_json_response(400, ['success' => false, 'error' => 'Request must include "stats", "character", and/or "daily_playtime".']);
}

// ---- Validate the stats object (before touching the database). ----
$clean = [];
if ($stats !== null) {
    if (!is_array($stats) || $stats === []) {
        grave_json_response(400, ['success' => false, 'error' => 'Missing or empty "stats" object.']);
    }

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

// ---- Validate the character action. ----
// Create: { ref, skin, name? }   End: { id|ref, ended: true, outcome? }
$characterAction = null; // null | 'create' | 'end'
$charRef = null;
$charId = null;
$charSkin = null;
$charName = null;
$charOutcome = null;

if ($character !== null) {
    if (!is_array($character)) {
        grave_json_response(400, ['success' => false, 'error' => '"character" must be an object.']);
    }

    if (!empty($character['ended'])) {
        $characterAction = 'end';

        if (isset($character['id'])) {
            if (!is_numeric($character['id']) || (int) $character['id'] <= 0) {
                grave_json_response(400, ['success' => false, 'error' => 'Character "id" must be a positive integer.']);
            }
            $charId = (int) $character['id'];
        } elseif (isset($character['ref']) && trim((string) $character['ref']) !== '') {
            $charRef = (string) $character['ref'];
        } else {
            grave_json_response(400, ['success' => false, 'error' => 'Ending a character requires "id" or "ref".']);
        }

        if (isset($character['outcome']) && trim((string) $character['outcome']) !== '') {
            $charOutcome = trim((string) $character['outcome']);
        }
    } elseif (isset($character['skin']) || isset($character['ref'])) {
        $characterAction = 'create';

        $charRef = isset($character['ref']) ? (string) $character['ref'] : '';
        $charSkin = isset($character['skin']) ? trim((string) $character['skin']) : '';
        if ($charRef === '') {
            grave_json_response(400, ['success' => false, 'error' => 'Character create requires a non-empty "ref" string.']);
        }
        if ($charSkin === '') {
            grave_json_response(400, ['success' => false, 'error' => 'Character create requires a non-empty "skin".']);
        }
        if (isset($character['name']) && trim((string) $character['name']) !== '') {
            $charName = trim((string) $character['name']);
        }
    } else {
        grave_json_response(400, [
            'success' => false,
            'error'   => 'Unrecognized "character" action: send {ref, skin} to create or {id|ref, ended: true} to end.',
        ]);
    }
}

// ---- Optional character context on a stats report (no aggregation yet). ----
$contextId = null;
$contextRef = null;
if (isset($input['character_id'])) {
    if (!is_numeric($input['character_id']) || (int) $input['character_id'] <= 0) {
        grave_json_response(400, ['success' => false, 'error' => '"character_id" must be a positive integer.']);
    }
    $contextId = (int) $input['character_id'];
} elseif (isset($input['character_ref'])) {
    if (trim((string) $input['character_ref']) === '') {
        grave_json_response(400, ['success' => false, 'error' => '"character_ref" must be a non-empty string.']);
    }
    $contextRef = (string) $input['character_ref'];
}

// ---- Validate daily playtime buckets (optional, per-character). ----
// [{ "date": "YYYY-MM-DD", "seconds": <int >= 0> }, ...]. The game sends
// absolute per-day totals; ingest max-merges each (character, date). Requires
// a character to attach to: a create/end in this post, or character context.
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

$contextCharacter = null;
if ($contextId !== null || $contextRef !== null) {
    $row = stats_find_character($pdo, $userId, $contextId, $contextRef);
    if ($row === null) {
        grave_json_response(404, ['success' => false, 'error' => 'Unknown character for this user.']);
    }
    $contextCharacter = stats_character_public($row);
}

// daily_playtime is per-character — it needs a character to attach to: either
// the character context on this post, or a character create/end in this post.
// (A create resolves its id inside the transaction below.)
if ($cleanPlaytime !== [] && $contextCharacter === null && $characterAction === null) {
    grave_json_response(400, [
        'success' => false,
        'error'   => '"daily_playtime" requires a character: send "character_id"/"character_ref", or a "character" create/end in the same post.',
    ]);
}

$characterOut = null;

$pdo->beginTransaction();
try {
    if ($characterAction === 'create') {
        $stmt = $pdo->prepare(
            'INSERT INTO characters (user_id, ref, name, skin) VALUES (:user_id, :ref, :name, :skin)'
        );
        $stmt->execute(['user_id' => $userId, 'ref' => $charRef, 'name' => $charName, 'skin' => $charSkin]);
        $newId = (int) $pdo->lastInsertId();

        // A new character is a new life.
        stats_apply($pdo, $userId, ['lives' => 1]);

        $characterOut = stats_find_character($pdo, $userId, $newId, null);
    } elseif ($characterAction === 'end') {
        $row = stats_find_character($pdo, $userId, $charId, $charRef);
        if ($row === null) {
            $pdo->rollBack();
            grave_json_response(404, ['success' => false, 'error' => 'Unknown character for this user.']);
        }

        $stmt = $pdo->prepare(
            'UPDATE characters
             SET ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
                 outcome  = COALESCE(:outcome, outcome)
             WHERE id = :id'
        );
        $stmt->execute(['outcome' => $charOutcome, 'id' => (int) $row['id']]);

        $characterOut = stats_find_character($pdo, $userId, (int) $row['id'], null);
    }

    if ($clean !== []) {
        stats_apply($pdo, $userId, $clean);

        // SURVIVOR VIEW (012): same values onto the character's own row when
        // this post has a character context (or created/ended one).
        $statsCharId = null;
        if ($contextCharacter !== null) {
            $statsCharId = (int) $contextCharacter['id'];
        } elseif ($characterOut !== null && isset($characterOut['id'])) {
            $statsCharId = (int) $characterOut['id'];
        }

        if ($statsCharId !== null && $statsCharId > 0) {
            stats_apply_character($pdo, $statsCharId, $clean);
        }
    }

    if ($cleanPlaytime !== []) {
        // Attach to the context character if given, else the character
        // created/ended in this same post. The pre-transaction guard
        // guarantees one of these exists.
        $playtimeCharId = ($contextCharacter !== null)
            ? (int) $contextCharacter['id']
            : (int) $characterOut['id'];

        $buckets = [];
        foreach ($cleanPlaytime as $date => $seconds) {
            $buckets[] = ['date' => $date, 'seconds' => $seconds];
        }
        stats_apply_playtime($pdo, $playtimeCharId, $buckets);
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
if ($characterOut !== null) {
    $response['character'] = stats_character_public($characterOut);
    if ($characterAction === 'create') {
        $response['character_id'] = $response['character']['id'];
    }
}
if ($contextCharacter !== null) {
    $response['character_context'] = $contextCharacter;
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

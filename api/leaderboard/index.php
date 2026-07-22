<?php
/**
 * THE DEAD LAST — API: survival-time leaderboard (public, read-only).
 *
 * GET /api/leaderboard
 *   -> { "success": true,
 *        "season": { season_start, season_end, ends_at, ends_at_unix,
 *                    server_time, server_unix, server_date },
 *        "top":   [ { rank, character: {id, name, skin, outcome, started_at},
 *                     username, seconds } ... ],   // LIMIT 25
 *        "today": [ same shape ] }                 // LIMIT 25
 *
 * The leaderboard metric (Boss, 2026-07-22) is PER-CHARACTER, PER-REAL-DAY
 * ACTIVE SURVIVAL TIME, reported by the game as daily_playtime buckets on stat
 * posts (see docs/game-stats-api.md) and stored in character_playtime.
 *
 *   "top"   = SUM(seconds) over each character's buckets whose date falls
 *             INSIDE the season window (settings season_start..season_end,
 *             inclusive). If no season is configured, falls back to ALL-TIME.
 *             Alive AND dead characters both rank (a dead legend keeps their
 *             place). LIMIT 25, highest total first.
 *   "today" = each character's bucket for the current SERVER date only.
 *             LIMIT 25, highest first.
 *
 * Both queries are covered by character_playtime's PK (character_id, date) plus
 * the date index — a range/equality scan and a grouped SUM, no N+1: the join to
 * characters/users happens once per already-aggregated row.
 *
 * PUBLIC: no auth. Mirrors the api/season public-read pattern — leaderboard
 * standings are shown on the title screen and the site /leaderboard page,
 * neither behind login, and the data isn't sensitive.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

const LEADERBOARD_LIMIT = 25;

/** Read one host setting (settings table) or null. */
function leaderboard_setting(PDO $db, string $key): ?string
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return ($value === false) ? null : (string) $value;
}

/** True when $value is a real calendar date in YYYY-MM-DD form. */
function leaderboard_valid_date(?string $value): bool
{
    if ($value === null || $value === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

/** Shape one aggregated leaderboard row for the response (rank added later). */
function leaderboard_row(array $row, int $rank): array
{
    return [
        'rank'      => $rank,
        'character' => [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'skin'       => (string) $row['skin'],
            'outcome'    => $row['outcome'],
            'started_at' => $row['started_at'],
        ],
        'username'  => (string) $row['username'],
        'seconds'   => (int) $row['seconds'],
    ];
}

try {
    $db = grave_db();

    $seasonStart = leaderboard_setting($db, 'season_start');
    $seasonEnd   = leaderboard_setting($db, 'season_end');

    $now = new DateTime();
    $serverDate = $now->format('Y-m-d');

    // ---- Season window: end moment = midnight after the end day. ----
    $endsAt     = null;
    $endsAtUnix = null;
    if (leaderboard_valid_date($seasonEnd)) {
        $endDt = DateTime::createFromFormat('Y-m-d H:i:s', $seasonEnd . ' 00:00:00');
        if ($endDt instanceof DateTime) {
            $endDt->modify('+1 day');
            $endsAt     = $endDt->format('Y-m-d H:i:s');
            $endsAtUnix = $endDt->getTimestamp();
        }
    }

    $hasWindow = leaderboard_valid_date($seasonStart) && leaderboard_valid_date($seasonEnd);

    // ---- TOP: SUM(seconds) per character over the season window (or all-time). ----
    // Group the buckets first (index-covered), then join out to the character
    // + owning account once per aggregated row. String date comparison is
    // valid because dates are zero-padded YYYY-MM-DD.
    if ($hasWindow) {
        $topSql =
            'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
                    agg.seconds AS seconds
             FROM (
                 SELECT character_id, SUM(seconds) AS seconds
                 FROM character_playtime
                 WHERE date BETWEEN :start AND :end
                 GROUP BY character_id
             ) agg
             JOIN characters c ON c.id = agg.character_id
             JOIN users u      ON u.id = c.user_id
             WHERE agg.seconds > 0
             ORDER BY agg.seconds DESC, c.id ASC
             LIMIT ' . LEADERBOARD_LIMIT;
        $topStmt = $db->prepare($topSql);
        $topStmt->execute(['start' => $seasonStart, 'end' => $seasonEnd]);
    } else {
        $topSql =
            'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
                    agg.seconds AS seconds
             FROM (
                 SELECT character_id, SUM(seconds) AS seconds
                 FROM character_playtime
                 GROUP BY character_id
             ) agg
             JOIN characters c ON c.id = agg.character_id
             JOIN users u      ON u.id = c.user_id
             WHERE agg.seconds > 0
             ORDER BY agg.seconds DESC, c.id ASC
             LIMIT ' . LEADERBOARD_LIMIT;
        $topStmt = $db->query($topSql);
    }

    $top = [];
    $rank = 0;
    foreach ($topStmt as $row) {
        $top[] = leaderboard_row($row, ++$rank);
    }

    // ---- TODAY: each character's bucket for the current server date. ----
    $todayStmt = $db->prepare(
        'SELECT c.id, c.name, c.skin, c.outcome, c.started_at, u.username,
                p.seconds AS seconds
         FROM character_playtime p
         JOIN characters c ON c.id = p.character_id
         JOIN users u      ON u.id = c.user_id
         WHERE p.date = :today AND p.seconds > 0
         ORDER BY p.seconds DESC, c.id ASC
         LIMIT ' . LEADERBOARD_LIMIT
    );
    $todayStmt->execute(['today' => $serverDate]);

    $today = [];
    $rank = 0;
    foreach ($todayStmt as $row) {
        $today[] = leaderboard_row($row, ++$rank);
    }
} catch (PDOException $e) {
    grave_json_response(500, ['success' => false, 'error' => 'Database error.']);
}

grave_json_response(200, [
    'success' => true,
    'season'  => [
        'season_start' => leaderboard_valid_date($seasonStart) ? $seasonStart : null,
        'season_end'   => leaderboard_valid_date($seasonEnd) ? $seasonEnd : null,
        'ends_at'      => $endsAt,
        'ends_at_unix' => $endsAtUnix,
        'server_time'  => $now->format('Y-m-d H:i:s'),
        'server_unix'  => $now->getTimestamp(),
        'server_date'  => $serverDate,
    ],
    'top'   => $top,
    'today' => $today,
]);

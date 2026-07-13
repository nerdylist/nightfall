<?php
/**
 * THE DEAD LAST — API: season window (public, read-only).
 *
 * GET /api/season
 *   -> { "success": true,
 *        "season_start": "2026-08-01",   // YYYY-MM-DD, or null if unset
 *        "season_end":   "2026-11-30",   // YYYY-MM-DD, or null if unset
 *        "ends_at":      "2026-12-01 00:00:00", // MIDNIGHT AFTER the end day
 *        "ends_at_unix": 1764547200,      // same moment, unix seconds (server TZ)
 *        "server_time":  "2026-07-13 02:40:11",
 *        "server_unix":  1752381611 }
 *
 * The season dates are authored in Keeper > Settings and stored in the host
 * `settings` table (keys season_start / season_end) as YYYY-MM-DD. The game's
 * title-screen ticker counts down to `ends_at` — MIDNIGHT at the END of the
 * end day (i.e. 00:00 the following day), so the last day is fully included.
 * (Date-only for now; true datetime + timezone comes with the full season
 * system — see game docs/season-clock-and-leaderboards.md.)
 *
 * PUBLIC: no auth. The countdown runs on the title screen before login, and
 * the dates aren't sensitive. Follows the api/feed public-read pattern.
 * `ends_at_unix` / `server_unix` let the client sync its countdown to the
 * SERVER clock (don't trust the local device clock for a competitive timer).
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../_respond.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    grave_json_response(405, ['success' => false, 'error' => 'Method not allowed.']);
}

/** Read one host setting (settings table) or null. */
function season_load_setting(PDO $db, string $key): ?string
{
    $stmt = $db->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return ($value === false) ? null : (string) $value;
}

/** True when $value is a real calendar date in YYYY-MM-DD form. */
function season_valid_date(?string $value): bool
{
    if ($value === null || $value === '') {
        return false;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);

    return $dt instanceof DateTime && $dt->format('Y-m-d') === $value;
}

try {
    $db = grave_db();

    $start = season_load_setting($db, 'season_start');
    $end   = season_load_setting($db, 'season_end');
} catch (PDOException $e) {
    grave_json_response(500, ['success' => false, 'error' => 'Database error.']);
}

// Compute the exact end moment: midnight AFTER the end day, so the last day
// is fully included (end 2026-11-30 -> countdown target 2026-12-01 00:00:00).
$endsAt     = null;
$endsAtUnix = null;

if (season_valid_date($end)) {
    $endDt = DateTime::createFromFormat('Y-m-d H:i:s', $end . ' 00:00:00');
    if ($endDt instanceof DateTime) {
        $endDt->modify('+1 day');                 // midnight after the last day
        $endsAt     = $endDt->format('Y-m-d H:i:s');
        $endsAtUnix = $endDt->getTimestamp();
    }
}

$now = new DateTime();

grave_json_response(200, [
    'success'      => true,
    'season_start' => season_valid_date($start) ? $start : null,
    'season_end'   => season_valid_date($end) ? $end : null,
    'ends_at'      => $endsAt,
    'ends_at_unix' => $endsAtUnix,
    'server_time'  => $now->format('Y-m-d H:i:s'),
    'server_unix'  => $now->getTimestamp(),
]);

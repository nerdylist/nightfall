<?php
/**
 * THE DEAD LAST — Season board registry (2026-07-22).
 *
 * One shared definition of every leaderboard BOARD backed by a player_stats
 * column, used by both GET /api/leaderboard?board=... and the /leaderboard
 * page tabs. The main survival-time board (character_playtime) is separate —
 * it ranks characters; these rank USERS.
 *
 *   column — player_stats column (whitelisted here, never from input)
 *   label  — display name (1950s-horror voice, per the spec doc)
 *   dir    — ASC boards are "lowest wins" (min semantics, 0 = unset)
 *   fmt    — count | money | duration | distance
 */

const TDL_BOARDS = [
    'maniac'      => ['column' => 'kills_hvh',              'label' => 'Maniac',           'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Most human-on-human kills.'],
    'hunter'      => ['column' => 'hunter_pure_kills',      'label' => 'Hunter',           'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Zombie kills — clean hands, no human blood.'],
    'allie'       => ['column' => 'allie_pure_kills',       'label' => 'Allie',            'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Zombie-vs-zombie kills with no human harmed.'],
    'glutton'     => ['column' => 'kills_zvh',              'label' => 'The Glutton',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Humans devoured as a zombie.'],
    'slugger'     => ['column' => 'bat_kills',              'label' => 'The Slugger',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Melee kills with a bat.'],
    'patientzero' => ['column' => 'humans_infected',        'label' => 'Patient Zero',     'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Humans infected.'],
    'horde'       => ['column' => 'biggest_horde_size',     'label' => 'Biggest Horde',    'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Largest horde led.'],
    'turned'      => ['column' => 'times_turned',           'label' => 'Times Turned',     'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Times gone over to the dead.'],
    'redemptions' => ['column' => 'redemptions',            'label' => 'Redemptions',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Times clawed back to the living.'],
    'truedeath'   => ['column' => 'true_deaths',            'label' => 'True Deaths',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Characters lost forever.'],
    'wealthy'     => ['column' => 'bank',                   'label' => 'Most Wealthy',     'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Fattest bank account right now.'],
    'banker'      => ['column' => 'banked_total',           'label' => 'The Banker',       'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Total cash extracted to the bank.'],
    'diedrich'    => ['column' => 'died_rich',              'label' => 'Died Rich',        'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Most cash dropped in a single death.'],
    'packrat'     => ['column' => 'chests_looted',          'label' => 'The Packrat',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Chests looted.'],
    'drifter'     => ['column' => 'distance_m',             'label' => 'The Drifter',      'dir' => 'DESC', 'fmt' => 'distance', 'blurb' => 'Ground covered on foot.'],
    'insomniac'   => ['column' => 'insomniac_seconds',      'label' => 'The Insomniac',    'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest life with no pause at all.'],
    'ghost'       => ['column' => 'kill_free_life_seconds', 'label' => 'The Ghost',        'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest life without a single kill.'],
    'lazarus'     => ['column' => 'lazarus_seconds',        'label' => 'Lazarus',          'dir' => 'ASC',  'fmt' => 'duration', 'blurb' => 'Fastest redemption after turning.'],
    'longwalk'    => ['column' => 'long_walk_seconds',      'label' => 'Long Walk Home',   'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest stretch as a zombie — and still made it back.'],
    'darwin'      => ['column' => 'fastest_death_seconds',  'label' => 'Darwin Award',     'dir' => 'ASC',  'fmt' => 'duration', 'blurb' => 'Fastest True Death of the season.'],
];

/** Top rows for one board: [ [username, value], ... ]. ASC boards skip 0 (unset). */
function tdl_board_rows(PDO $db, string $key, int $limit = 10): array
{
    if (!isset(TDL_BOARDS[$key])) {
        return [];
    }

    $def = TDL_BOARDS[$key];
    $col = $def['column']; // registry-only — never user input
    $dir = $def['dir'] === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT u.username, s.{$col} AS value
            FROM player_stats s
            JOIN users u ON u.id = s.user_id
            WHERE s.{$col} > 0
            ORDER BY s.{$col} {$dir}, u.username ASC
            LIMIT " . (int) $limit;

    return $db->query($sql)->fetchAll();
}

/** Format a board value per its fmt tag. */
function tdl_board_format(array $def, int $value): string
{
    switch ($def['fmt']) {
        case 'money':
            return '$' . number_format($value);
        case 'duration':
            $h = intdiv($value, 3600);
            $m = intdiv($value % 3600, 60);
            $sec = $value % 60;
            if ($h > 0) { return $h . 'h ' . $m . 'm'; }
            if ($m > 0) { return $m . 'm ' . $sec . 's'; }
            return $sec . 's';
        case 'distance':
            return $value >= 1000
                ? number_format($value / 1000, 1) . ' km'
                : $value . ' m';
        default:
            return number_format($value);
    }
}

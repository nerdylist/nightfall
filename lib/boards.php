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
    'maniac'      => ['column' => 'kills_hvh',              'label' => 'Maniac',           'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Most human-on-human kills.', 'icon' => '/assets/images/leaderboards/icon-_0009_maniac.png'],
    'hunter'      => ['column' => 'hunter_pure_kills',      'label' => 'Hunter',           'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Zombie kills — clean hands, no human blood.', 'icon' => '/assets/images/leaderboards/icon-_0008_hunter.png'],
    'allie'       => ['column' => 'allie_pure_kills',       'label' => 'Allie',            'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Zombie-vs-zombie kills with no human harmed.', 'icon' => '/assets/images/leaderboards/icon-_0007_allies.png'],
    'glutton'     => ['column' => 'kills_zvh',              'label' => 'The Glutton',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Humans devoured as a zombie.', 'icon' => '/assets/images/leaderboards/icon-_0006_the_glutton.png'],
    'slugger'     => ['column' => 'bat_kills',              'label' => 'The Slugger',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Melee kills with a bat.', 'icon' => '/assets/images/leaderboards/icon-_0005_the_slugger.png'],
    'patientzero' => ['column' => 'humans_infected',        'label' => 'Patient Zero',     'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Humans infected.', 'icon' => '/assets/images/leaderboards/icon-_0004_patient_zero.png'],
    'horde'       => ['column' => 'biggest_horde_size',     'label' => 'Biggest Horde',    'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Largest horde led.', 'icon' => '/assets/images/leaderboards/icon-_0003_biggest_horde.png'],
    'turned'      => ['column' => 'times_turned',           'label' => 'Times Turned',     'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Times gone over to the dead.', 'icon' => '/assets/images/leaderboards/icon-_0002_times_turned.png'],
    'redemptions' => ['column' => 'redemptions',            'label' => 'Redemptions',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Times clawed back to the living.', 'icon' => '/assets/images/leaderboards/icon-_0001_redemptions.png'],
    'truedeath'   => ['column' => 'true_deaths',            'label' => 'True Deaths',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Characters lost forever.', 'icon' => '/assets/images/leaderboards/icon-_0000_true_deaths.png'],
    'wealthy'     => ['column' => 'bank',                   'label' => 'Most Wealthy',     'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Fattest bank account right now.', 'icon' => '/assets/images/leaderboards/icon-_0019_most_wealthy.png'],
    'banker'      => ['column' => 'banked_total',           'label' => 'The Banker',       'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Total cash extracted to the bank.', 'icon' => '/assets/images/leaderboards/icon-_0018_the_banker.png'],
    'diedrich'    => ['column' => 'died_rich',              'label' => 'Died Rich',        'dir' => 'DESC', 'fmt' => 'money',    'blurb' => 'Most cash dropped in a single death.', 'icon' => '/assets/images/leaderboards/icon-_0017_died_rich.png'],
    'packrat'     => ['column' => 'chests_looted',          'label' => 'The Packrat',      'dir' => 'DESC', 'fmt' => 'count',    'blurb' => 'Chests looted.', 'icon' => '/assets/images/leaderboards/icon-_0016_the_packrat.png'],
    'drifter'     => ['column' => 'distance_m',             'label' => 'The Drifter',      'dir' => 'DESC', 'fmt' => 'distance', 'blurb' => 'Ground covered on foot.', 'icon' => '/assets/images/leaderboards/icon-_0015_the_drifter.png'],
    'insomniac'   => ['column' => 'insomniac_seconds',      'label' => 'The Insomniac',    'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest life with no pause at all.', 'icon' => '/assets/images/leaderboards/icon-_0014_the_insomniac.png'],
    'ghost'       => ['column' => 'kill_free_life_seconds', 'label' => 'The Ghost',        'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest life without a single kill.', 'icon' => '/assets/images/leaderboards/icon-_0013_the_ghost.png'],
    'lazarus'     => ['column' => 'lazarus_seconds',        'label' => 'Lazarus',          'dir' => 'ASC',  'fmt' => 'duration', 'blurb' => 'Fastest redemption after turning.', 'icon' => '/assets/images/leaderboards/icon-_0012_lazarus.png'],
    'longwalk'    => ['column' => 'long_walk_seconds',      'label' => 'Long Walk Home',   'dir' => 'DESC', 'fmt' => 'duration', 'blurb' => 'Longest stretch as a zombie — and still made it back.', 'icon' => '/assets/images/leaderboards/icon-_0011_long_walk_home.png'],
    'darwin'      => ['column' => 'fastest_death_seconds',  'label' => 'Darwin Award',     'dir' => 'ASC',  'fmt' => 'duration', 'blurb' => 'Fastest True Death of the season.', 'icon' => '/assets/images/leaderboards/icon-_0010_darwin_award.png'],
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

/**
 * SURVIVOR rows for one board: [ [name, skin, outcome, username, value] ].
 * Ranks individual characters from character_stats (012).
 */
function tdl_board_rows_survivor(PDO $db, string $key, int $limit = 10): array
{
    if (!isset(TDL_BOARDS[$key])) {
        return [];
    }

    $def = TDL_BOARDS[$key];
    $col = $def['column']; // registry-only — never user input
    $dir = $def['dir'] === 'ASC' ? 'ASC' : 'DESC';

    $sql = "SELECT c.name, c.skin, c.outcome, u.username, s.{$col} AS value
            FROM character_stats s
            JOIN characters c ON c.id = s.character_id
            JOIN users u      ON u.id = c.user_id
            WHERE s.{$col} > 0
            ORDER BY s.{$col} {$dir}, c.id ASC
            LIMIT " . (int) $limit;

    return $db->query($sql)->fetchAll();
}

/**
 * ALL view: survivor rows + user rows merged into one ranking (each row
 * tagged 'who' = survivor|user), sorted by value per the board direction.
 */
function tdl_board_rows_all(PDO $db, string $key, int $limit = 10): array
{
    $rows = [];
    foreach (tdl_board_rows($db, $key, $limit) as $r) {
        $rows[] = ['who' => 'user', 'label' => '@' . $r['username'],
                   'sub' => '', 'value' => (int) $r['value']];
    }
    foreach (tdl_board_rows_survivor($db, $key, $limit) as $r) {
        $name = trim((string) ($r['name'] ?? ''));
        $rows[] = ['who' => 'survivor',
                   'label' => $name !== '' ? $name : (string) $r['skin'],
                   'sub' => '@' . $r['username'], 'value' => (int) $r['value']];
    }

    $asc = TDL_BOARDS[$key]['dir'] === 'ASC';
    usort($rows, fn ($a, $b) => $asc ? $a['value'] <=> $b['value'] : $b['value'] <=> $a['value']);

    return array_slice($rows, 0, $limit);
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

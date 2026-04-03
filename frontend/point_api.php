<?php
/**
 * Padel Point API
 * POST /padel/point_api.php?player=TEAM_ID
 * PUT  /padel/point_api.php?player=TEAM_ID
 *
 * Returns JSON. No HTML.
 *
 * Setup: paste your Firebase Database Secret into $DB_SECRET below.
 * Find it in Firebase Console → Project Settings → Service accounts → Database secrets.
 */

// ── Config ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/config.php'; // DB_URL and DB_SECRET defined there (gitignored)

// ── Bootstrap ─────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST or PUT.']);
    exit;
}

$teamId = trim($_GET['player'] ?? '');
if (!$teamId) {
    $body   = json_decode(file_get_contents('php://input'), true);
    $teamId = trim($body['player'] ?? '');
}
if (!$teamId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing ?player= parameter (team Firebase key).']);
    exit;
}

// ── Firebase REST helpers ─────────────────────────────────────────────────
function fb_get(string $path): mixed {
    $url = DB_URL . $path . '.json?auth=' . DB_SECRET;
    $res = file_get_contents($url);
    return $res !== false ? json_decode($res, true) : null;
}

function fb_put(string $path, mixed $data): bool {
    $ctx = stream_context_create(['http' => [
        'method'  => 'PUT',
        'header'  => 'Content-Type: application/json',
        'content' => json_encode($data, JSON_UNESCAPED_UNICODE),
    ]]);
    $url = DB_URL . $path . '.json?auth=' . DB_SECRET;
    return file_get_contents($url, false, $ctx) !== false;
}

// ── Scoring engine ────────────────────────────────────────────────────────
function to_arr(mixed $v): array {
    if (is_array($v)) return array_values($v);
    return [];
}

function is_golden(array $s): bool {
    return ($s['_info']['deuce'] ?? 'advantage') === 'golden';
}

function start_tiebreak(array &$s): void {
    $s['inTiebreak'] = true;
    $s['tbA'] = 0; $s['tbB'] = 0; $s['_tbCount'] = 0;
    $s['server'] = $s['server'] === 'A' ? 'B' : 'A';
}

function check_set_end(array &$s): void {
    $n = ($s['_info']['format'] ?? 'best3') === 'best5' ? 3 : 2;
    if ($s['setsA'] >= $n || $s['setsB'] >= $n) $s['finished'] = true;
}

function record_set(array &$s, ?string $tb = null): void {
    $entry = ['a' => $s['gamesA'], 'b' => $s['gamesB']];
    if ($tb !== null) $entry['tb'] = $tb;
    $s['setHistory'][] = $entry;
}

function next_set_server(array &$s): void {
    $s['server'] = $s['server'] === 'A' ? 'B' : 'A';
}

function rotate_server(array &$s): void {
    if ($s['server'] === 'A') {
        $s['serverIdxA'] = ($s['serverIdxA'] + 1) % 2;
        $s['server'] = 'B';
    } else {
        $s['serverIdxB'] = ($s['serverIdxB'] + 1) % 2;
        $s['server'] = 'A';
    }
}

function game_won(array &$s, string $team): void {
    if ($team === 'A') $s['gamesA']++; else $s['gamesB']++;
    $s['pointsA'] = 0; $s['pointsB'] = 0;
    $tbOn = true; // tiebreak always enabled

    if ($tbOn && $s['gamesA'] === 6 && $s['gamesB'] === 6) {
        start_tiebreak($s);
    } elseif (($s['gamesA'] >= 6 || $s['gamesB'] >= 6) && abs($s['gamesA'] - $s['gamesB']) >= 2) {
        if ($s['gamesA'] > $s['gamesB']) $s['setsA']++; else $s['setsB']++;
        record_set($s);
        $s['gamesA'] = 0; $s['gamesB'] = 0;
        next_set_server($s); check_set_end($s);
    } else {
        rotate_server($s);
    }
}

function give_point(array $s, string $team): array {
    if ($s['finished']) return $s;

    // Push history
    $s['history']    = to_arr($s['history'] ?? []);
    $s['setHistory'] = to_arr($s['setHistory'] ?? []);
    $snap = $s; unset($snap['history']);
    $s['history'][] = $snap;
    if (count($s['history']) > 200) array_shift($s['history']);

    if ($s['inTiebreak']) {
        if ($team === 'A') $s['tbA']++; else $s['tbB']++;
        $s['_tbCount']++;
        if ($s['_tbCount'] === 1)
            $s['server'] = $s['server'] === 'A' ? 'B' : 'A';
        elseif ($s['_tbCount'] > 1 && $s['_tbCount'] % 2 === 1)
            $s['server'] = $s['server'] === 'A' ? 'B' : 'A';

        if (($s['tbA'] >= 7 || $s['tbB'] >= 7) && abs($s['tbA'] - $s['tbB']) >= 2) {
            $winner = $s['tbA'] > $s['tbB'] ? 'A' : 'B';
            if ($winner === 'A') $s['setsA']++; else $s['setsB']++;
            record_set($s, "{$s['tbA']}-{$s['tbB']}");
            $s['gamesA'] = 0; $s['gamesB'] = 0;
            $s['pointsA'] = 0; $s['pointsB'] = 0;
            $s['inTiebreak'] = false; $s['tbA'] = 0; $s['tbB'] = 0; $s['_tbCount'] = 0;
            next_set_server($s); check_set_end($s);
        }
        return $s;
    }

    if ($team === 'A') $s['pointsA']++; else $s['pointsB']++;
    $a = $s['pointsA']; $b = $s['pointsB'];

    // Golden point: at deuce (3-3) next point wins
    if (is_golden($s) && $a >= 3 && $b >= 3 && $a !== $b) {
        game_won($s, $a > $b ? 'A' : 'B');
        return $s;
    }

    // Standard advantage
    if (($a >= 4 || $b >= 4) && abs($a - $b) >= 2) {
        game_won($s, $a > $b ? 'A' : 'B');
    }

    return $s;
}

// ── Main ──────────────────────────────────────────────────────────────────
$games = fb_get('/padel-score/games');
if (!is_array($games)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Could not read games from Firebase.']);
    exit;
}

$foundGameId = null;
$foundTeam   = null;
$foundGame   = null;

foreach ($games as $gid => $g) {
    if (!empty($g['finished'])) continue;
    $info = $g['_info'] ?? [];
    if (($info['teamAId'] ?? '') === $teamId) { $foundGameId = $gid; $foundTeam = 'A'; $foundGame = $g; break; }
    if (($info['teamBId'] ?? '') === $teamId) { $foundGameId = $gid; $foundTeam = 'B'; $foundGame = $g; break; }
}

if (!$foundGameId) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'No active game found for this team ID.', 'teamId' => $teamId]);
    exit;
}

// Normalise Firebase arrays-as-objects
$foundGame['history']    = array_values($foundGame['history']    ?? []);
$foundGame['setHistory'] = array_values($foundGame['setHistory'] ?? []);
if (isset($foundGame['_info']['playersA'])) $foundGame['_info']['playersA'] = array_values($foundGame['_info']['playersA']);
if (isset($foundGame['_info']['playersB'])) $foundGame['_info']['playersB'] = array_values($foundGame['_info']['playersB']);

$updated = give_point($foundGame, $foundTeam);

// Strip _meta so live_dommer listener does not mistake it for its own write
unset($updated['_meta']);

if (!fb_put('/padel-score/games/' . $foundGameId, $updated)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Failed to write updated game to Firebase.']);
    exit;
}

$info     = $updated['_info'] ?? [];
$teamName = $foundTeam === 'A' ? ($info['teamA'] ?? 'Hold A') : ($info['teamB'] ?? 'Hold B');

http_response_code(200);
echo json_encode([
    'ok'       => true,
    'gameId'   => $foundGameId,
    'team'     => $foundTeam,
    'teamName' => $teamName,
    'gameName' => $info['name'] ?? $foundGameId,
    'finished' => !empty($updated['finished']),
    'score'    => [
        'setsA'   => $updated['setsA']   ?? 0,
        'setsB'   => $updated['setsB']   ?? 0,
        'gamesA'  => $updated['gamesA']  ?? 0,
        'gamesB'  => $updated['gamesB']  ?? 0,
        'pointsA' => $updated['pointsA'] ?? 0,
        'pointsB' => $updated['pointsB'] ?? 0,
    ],
], JSON_UNESCAPED_UNICODE);

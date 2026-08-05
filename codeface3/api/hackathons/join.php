<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$id = (int)($b['hackathon_id'] ?? 0);
$action = (string)($b['action'] ?? '');

$hack = db_one('SELECT id FROM hackathons WHERE id = ?', [$id]);
if (!$hack) json_error('Hackathon not found.', 404);

$pdo = db();
if ($action === 'join') {
    $sql = db_driver() === 'mysql'
        ? 'INSERT IGNORE INTO hackathon_participants (hackathon_id, user_id) VALUES (?, ?)'
        : 'INSERT OR IGNORE INTO hackathon_participants (hackathon_id, user_id) VALUES (?, ?)';
    $pdo->prepare($sql)->execute([$id, $me['id']]);
} elseif ($action === 'leave') {
    $pdo->prepare('DELETE FROM hackathon_participants WHERE hackathon_id = ? AND user_id = ?')->execute([$id, $me['id']]);
} else {
    json_error('Unknown action.');
}

$joined = (bool)db_one('SELECT user_id FROM hackathon_participants WHERE hackathon_id = ? AND user_id = ?', [$id, $me['id']]);
$count  = (int)(db_one('SELECT COUNT(*) AS c FROM hackathon_participants WHERE hackathon_id = ?', [$id])['c'] ?? 0);

json_response(['ok' => true, 'joined' => $joined, 'count' => $count]);

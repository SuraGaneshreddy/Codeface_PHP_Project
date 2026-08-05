<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('GET');

$row = db_one('SELECT status, room_code, language, difficulty FROM matchmaking_queue WHERE user_id = ?', [(int)$me['id']]);
if (!$row || $row['status'] === 'cancelled') {
    json_response(['ok' => true, 'status' => 'idle']);
}
if ($row['status'] === 'matched' && $row['room_code']) {
    json_response(['ok' => true, 'status' => 'matched', 'room_code' => $row['room_code']]);
}
json_response(['ok' => true, 'status' => 'waiting', 'language' => $row['language'], 'difficulty' => $row['difficulty']]);

<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$room = room_by_code((string)($b['code'] ?? ''));
if (!$room) json_error('Room not found.', 404);

$body = trim((string)($b['body'] ?? ''));
if ($body === '' || mb_strlen($body) > 500) json_error('Messages must be 1–500 characters.');

$roomId = (int)$room['id'];
$member = db_one('SELECT user_id FROM room_members WHERE room_id = ? AND user_id = ? AND left_at IS NULL', [$roomId, $me['id']]);
if (!$member) json_error('You are not a member of this room.', 403);

$st = db()->prepare('INSERT INTO chat_messages (room_id, user_id, body, created_at) VALUES (?, ?, ?, ?)');
$st->execute([$roomId, $me['id'], $body, now()]);
$id = (int)db()->lastInsertId();

room_touch_member($roomId, (int)$me['id']);

json_response([
    'ok' => true,
    'message' => [
        'id'         => $id,
        'username'   => $me['username'],
        'color'      => $me['avatar_color'],
        'body'       => $body,
        'created_at' => now(),
    ],
]);

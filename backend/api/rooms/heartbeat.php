<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$room = room_by_code((string)($b['code'] ?? ''));
if (!$room) json_error('Room not found.', 404);

room_touch_member((int)$room['id'], (int)$me['id']);

$online = array_values(array_filter(room_members((int)$room['id']), function ($m) { return $m['online']; }));
json_response(['ok' => true, 'online' => count($online)]);

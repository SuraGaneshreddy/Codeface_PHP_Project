<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('GET');

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$room = $code !== '' ? room_by_code($code) : null;
if (!$room) json_error('Room not found.', 404);

room_join((int)$room['id'], (int)$me['id'], $room['owner_username'] === $me['username'] ? 'owner' : 'participant');

json_response(['ok' => true] + room_state_payload($room, (int)$me['id']));

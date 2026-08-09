<?php
require __DIR__ . '/../../lib/bootstrap.php';

// Used by regular fetch AND by navigator.sendBeacon on page unload (form-encoded body).
$me = require_login_json();
require_method('POST');
verify_csrf(true);

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
if ($code === '') {
    $b = read_json_body();
    $code = strtoupper(trim((string)($b['code'] ?? '')));
}
$room = $code !== '' ? room_by_code($code) : null;
if (!$room) json_error('Room not found.', 404);

room_leave((int)$room['id'], (int)$me['id']);
json_response(['ok' => true]);

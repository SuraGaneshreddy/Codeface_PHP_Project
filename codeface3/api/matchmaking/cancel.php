<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$st = db()->prepare("UPDATE matchmaking_queue SET status = 'cancelled' WHERE user_id = ? AND status = 'waiting'");
$st->execute([(int)$me['id']]);

json_response(['ok' => true, 'status' => 'idle']);

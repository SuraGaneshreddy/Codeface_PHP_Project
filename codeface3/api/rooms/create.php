<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$name = trim((string)($b['name'] ?? ''));
$language = (string)($b['language'] ?? 'javascript');
$problemId = (int)($b['problem_id'] ?? 0);

if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
    json_error('Room name must be 2–60 characters.');
}
if (!array_key_exists($language, ROOM_LANGUAGES)) {
    json_error('Unsupported language.');
}
$problem = null;
if ($problemId > 0) {
    $problem = db_one('SELECT * FROM problems WHERE id = ?', [$problemId]);
    if (!$problem) json_error('That problem does not exist.');
}

$pdo = db();
$code = '';
for ($i = 0; $i < 10; $i++) {
    $code = room_code();
    if (!db_one('SELECT id FROM rooms WHERE code = ?', [$code])) break;
}

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare('INSERT INTO rooms (code, name, owner_id, problem_id, language, is_live, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
    $ins->execute([$code, $name, $me['id'], $problem['id'] ?? null, $language, now()]);
    $roomId = (int)$pdo->lastInsertId();

    $pad = $pdo->prepare('INSERT INTO room_pads (room_id, language, content, version, last_editor_id) VALUES (?, ?, ?, 0, NULL)');
    foreach (room_default_pads($problem) as $lang => $content) {
        $pad->execute([$roomId, $lang, $content]);
    }
    $mem = $pdo->prepare('INSERT INTO room_members (room_id, user_id, role, joined_at, last_seen) VALUES (?, ?, ?, ?, ?)');
    $mem->execute([$roomId, $me['id'], 'owner', now(), now()]);
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_error('Could not create the room. Please try again.', 500);
}

json_response(['ok' => true, 'code' => $code]);

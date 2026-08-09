<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$room = room_by_code((string)($b['code'] ?? ''));
if (!$room) json_error('Room not found.', 404);

$language = (string)($b['language'] ?? '');
if (!array_key_exists($language, ROOM_LANGUAGES)) json_error('Unsupported language.');

$baseVersion = (int)($b['base_version'] ?? -1);
$content = (string)($b['content'] ?? '');
if (strlen($content) > 200000) json_error('Pad content is too large (200 KB cap).');

$roomId = (int)$room['id'];

// Must be a current member
$member = db_one('SELECT user_id FROM room_members WHERE room_id = ? AND user_id = ? AND left_at IS NULL', [$roomId, $me['id']]);
if (!$member) json_error('You are not a member of this room.', 403);

$pdo = db();
$forUpdate = db_driver() === 'mysql' ? ' FOR UPDATE' : '';

$pdo->beginTransaction();
$st = $pdo->prepare('SELECT version, content, last_editor_id FROM room_pads WHERE room_id = ? AND language = ?' . $forUpdate);
$st->execute([$roomId, $language]);
$pad = $st->fetch();

// Rooms created before a language existed have no pad row yet — create it lazily.
if (!$pad) {
    if ($baseVersion !== 0) {
        $pdo->rollBack();
        json_error('Pad not initialized.', 409, ['version' => 0, 'content' => '', 'editor' => null]);
    }
    $ins = $pdo->prepare('INSERT INTO room_pads (room_id, language, content, version, last_editor_id, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
    $ins->execute([$roomId, $language, $content, $me['id'], now()]);
    $pdo->commit();
    room_touch_member($roomId, (int)$me['id']);
    json_response(['ok' => true, 'version' => 1]);
}

$current = (int)$pad['version'];
if ($baseVersion !== $current) {
    $pdo->rollBack();
    $editor = null;
    if (!empty($pad['last_editor_id'])) {
        $editor = db_one('SELECT username FROM users WHERE id = ?', [(int)$pad['last_editor_id']]);
    }
    json_error('Out of sync — reloading latest.', 409, [
        'version' => $current,
        'content' => $pad['content'],
        'editor'  => $editor['username'] ?? null,
    ]);
}

$up = $pdo->prepare('UPDATE room_pads SET content = ?, version = ?, last_editor_id = ?, updated_at = ? WHERE room_id = ? AND language = ?');
$up->execute([$content, $current + 1, $me['id'], now(), $roomId, $language]);
$pdo->commit();

room_touch_member($roomId, (int)$me['id']);

json_response(['ok' => true, 'version' => $current + 1]);

<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$language   = (string)($b['language'] ?? 'javascript');
$difficulty = (string)($b['difficulty'] ?? 'medium');

if (!array_key_exists($language, ROOM_LANGUAGES)) json_error('Unsupported language.');
if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) json_error('Unsupported difficulty.');

$pdo = db();
$uid = (int)$me['id'];

// Expire abandoned queue entries (>10 min)
$pdo->prepare("UPDATE matchmaking_queue SET status = 'cancelled' WHERE status = 'waiting' AND created_at < ?")
    ->execute([ts(time() - 600)]);

// Idempotent: already matched?
$existing = db_one('SELECT * FROM matchmaking_queue WHERE user_id = ?', [$uid]);
if ($existing && $existing['status'] === 'matched' && $existing['room_code']) {
    json_response(['ok' => true, 'status' => 'matched', 'room_code' => $existing['room_code']]);
}

// Look for a waiting opponent: same language + difficulty, closest rating
$opponent = db_one(
    "SELECT q.id AS queue_id, q.user_id, u.username, u.rating
     FROM matchmaking_queue q JOIN users u ON u.id = q.user_id
     WHERE q.status = 'waiting' AND q.user_id != ? AND q.language = ? AND q.difficulty = ?
     ORDER BY ABS(u.rating - ?) ASC, q.created_at ASC
     LIMIT 1",
    [$uid, $language, $difficulty, (int)$me['rating']]
);

if ($opponent) {
    $pdo->beginTransaction();
    try {
        // pick a random problem of the chosen difficulty
        $problem = db_one('SELECT * FROM problems WHERE difficulty = ? AND ai_user_id IS NULL ORDER BY ' . db_random() . ' LIMIT 1', [$difficulty]);

        $oppName = $opponent['username'];
        $name = 'Match: ' . $oppName . ' × ' . $me['username'];
        $code = room_code();

        $ins = $pdo->prepare('INSERT INTO rooms (code, name, owner_id, problem_id, language, is_live, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)');
        $ins->execute([$code, $name, $uid, $problem['id'] ?? null, $language, now()]);
        $roomId = (int)$pdo->lastInsertId();

        $pad = $pdo->prepare('INSERT INTO room_pads (room_id, language, content, version, last_editor_id) VALUES (?, ?, ?, 0, NULL)');
        foreach (room_default_pads($problem ?: null) as $lang => $content) {
            $pad->execute([$roomId, $lang, $content]);
        }
        $mem = $pdo->prepare('INSERT INTO room_members (room_id, user_id, role, joined_at, last_seen) VALUES (?, ?, ?, ?, ?)');
        $mem->execute([$roomId, $uid, 'owner', now(), now()]);
        $mem->execute([$roomId, (int)$opponent['user_id'], 'participant', now(), ts(time() - 30)]);

        // mark both queue rows
        $mark = $pdo->prepare("UPDATE matchmaking_queue SET status = 'matched', room_code = ? WHERE user_id = ?");
        $mark->execute([$code, $uid]);
        $mark->execute([$code, (int)$opponent['user_id']]);

        // my own row may not have existed yet
        if (!$existing) {
            $insq = $pdo->prepare("INSERT INTO matchmaking_queue (user_id, language, difficulty, status, room_code) VALUES (?, ?, ?, 'matched', ?)");
            $insq->execute([$uid, $language, $difficulty, $code]);
            // if the row was created by the UPDATE above, this insert will fail on the UNIQUE key — catch below
        }
        $pdo->commit();
        json_response(['ok' => true, 'status' => 'matched', 'room_code' => $code, 'opponent' => $oppName]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // likely the UNIQUE race on the insert — recover by returning current state
        $mine = db_one('SELECT status, room_code FROM matchmaking_queue WHERE user_id = ?', [$uid]);
        if ($mine && $mine['status'] === 'matched') {
            json_response(['ok' => true, 'status' => 'matched', 'room_code' => $mine['room_code']]);
        }
        json_error('Matchmaking hiccup — please try again.', 500);
    }
}

// No opponent: enqueue (or refresh) my waiting row
if (db_driver() === 'mysql') {
    $pdo->prepare(
        "INSERT INTO matchmaking_queue (user_id, language, difficulty, status, room_code, created_at)
         VALUES (?, ?, ?, 'waiting', NULL, ?)
         ON DUPLICATE KEY UPDATE language = VALUES(language), difficulty = VALUES(difficulty),
                                 status = 'waiting', room_code = NULL, created_at = VALUES(created_at)"
    )->execute([$uid, $language, $difficulty, now()]);
} else {
    $pdo->prepare(
        "INSERT INTO matchmaking_queue (user_id, language, difficulty, status, room_code, created_at)
         VALUES (?, ?, ?, 'waiting', NULL, ?)
         ON CONFLICT(user_id) DO UPDATE SET language = excluded.language, difficulty = excluded.difficulty,
                                 status = 'waiting', room_code = NULL, created_at = excluded.created_at"
    )->execute([$uid, $language, $difficulty, now()]);
}

json_response(['ok' => true, 'status' => 'waiting']);

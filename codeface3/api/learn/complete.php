<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$lessonId = (int)($b['lesson_id'] ?? 0);
$lesson = db_one('SELECT id, track FROM learn_lessons WHERE id = ?', [$lessonId]);
if (!$lesson) json_error('Lesson not found.', 404);

$pdo = db();
$existing = db_one('SELECT lesson_id FROM learn_progress WHERE user_id = ? AND lesson_id = ?', [$me['id'], $lessonId]);
if ($existing) {
    $pdo->prepare('DELETE FROM learn_progress WHERE user_id = ? AND lesson_id = ?')->execute([$me['id'], $lessonId]);
    $completed = false;
} else {
    $sql = db_driver() === 'mysql'
        ? 'INSERT IGNORE INTO learn_progress (user_id, lesson_id, completed_at) VALUES (?, ?, ?)'
        : 'INSERT OR IGNORE INTO learn_progress (user_id, lesson_id, completed_at) VALUES (?, ?, ?)';
    $pdo->prepare($sql)->execute([$me['id'], $lessonId, now()]);
    $completed = true;
}

$trackDone = (int)(db_one(
    'SELECT COUNT(*) AS c FROM learn_progress p JOIN learn_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND l.track = ?',
    [$me['id'], $lesson['track']]
)['c'] ?? 0);
$trackTotal = (int)(db_one('SELECT COUNT(*) AS c FROM learn_lessons WHERE track = ?', [$lesson['track']])['c'] ?? 0);

json_response([
    'ok'          => true,
    'completed'   => $completed,
    'track_done'  => $trackDone,
    'track_total' => $trackTotal,
]);

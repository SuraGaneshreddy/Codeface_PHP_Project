<?php
require __DIR__ . '/../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

$b = read_json_body();
$problemId = (int)($b['problem_id'] ?? 0);
$code      = (string)($b['code'] ?? '');
$passed    = (int)($b['passed'] ?? 0);
$total     = (int)($b['total'] ?? 0);
$runtimeMs = isset($b['runtime_ms']) ? (float)$b['runtime_ms'] : null;

$problem = db_one('SELECT id, points, ai_user_id FROM problems WHERE id = ?', [$problemId]);
if ($problem && $problem['ai_user_id'] !== null && (int)$problem['ai_user_id'] !== (int)$me['id']) $problem = null; // AI rows are private to their owner
if (!$problem) json_error('Problem not found.', 404);
if (strlen($code) > 100000) json_error('Submission is too large (100 KB cap).');
if ($total <= 0 || $passed < 0 || $passed > $total) json_error('Invalid test counts.');

$status = $passed === $total ? 'pass' : 'fail';

$hadPass = (bool)db_one(
    "SELECT id FROM submissions WHERE user_id = ? AND problem_id = ? AND status = 'pass' LIMIT 1",
    [$me['id'], $problemId]
);

$st = db()->prepare('INSERT INTO submissions (user_id, problem_id, status, code, passed, total, runtime_ms, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$st->execute([$me['id'], $problemId, $status, $code, $passed, $total, $runtimeMs, now()]);

$firstSolve = false;
$newRating = (int)$me['rating'];
if ($status === 'pass' && !$hadPass) {
    db()->prepare('UPDATE users SET rating = rating + ? WHERE id = ?')->execute([(int)$problem['points'], $me['id']]);
    $firstSolve = true;
    $newRating += (int)$problem['points'];
}

json_response([
    'ok'          => true,
    'status'      => $status,
    'first_solve' => $firstSolve,
    'points'      => $firstSolve ? (int)$problem['points'] : 0,
    'rating'      => $newRating,
]);

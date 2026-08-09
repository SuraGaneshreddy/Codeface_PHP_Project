<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

/* 🔒 Practice gate — even a hand-crafted request can't bank a refactor score early. */
$gateSolved = cf_solved_problems_count((int)$me['id']);
if ($gateSolved < cf_practice_gate()) {
    json_error('Refactor Gym unlocks after ' . cf_practice_gate() . " solved practice problems — you have {$gateSolved}/" . cf_practice_gate() . '.', 403);
}

$b = read_json_body();
$slug = (string)($b['slug'] ?? '');
$chal = cf_get_refactor($slug);
if (!$chal) {
    /* AI repo: owner's own slug + unlocked batch only. */
    if (preg_match('/^air(\d+)b(\d+)-/', $slug, $m) && (int)$m[1] === (int)$me['id']) {
        [$maxB] = cf_ai_refactor_unlock(db(), (int)$me['id']);
        if ((int)$m[2] <= $maxB) $chal = cf_ai_refactor((int)$me['id'], $slug);
    }
    if (!$chal) json_error('Challenge not found.', 404);
}

$score = (int)($b['score'] ?? 0);
$testsPassed = (int)($b['tests_passed'] ?? 0);
$testsTotal = (int)($b['tests_total'] ?? 0);
if ($testsTotal < 1 || $testsPassed < 0 || $testsPassed > $testsTotal) json_error('Invalid test counts.');
if ($testsPassed < $testsTotal) json_error('All safety tests must pass before submitting.');
if ($testsTotal !== count($chal['checks'])) json_error('Test count does not match this challenge.');
$score = max(0, min(100, $score));
$metrics = json_encode($b['metrics'] ?? [], JSON_UNESCAPED_SLASHES);
if (strlen($metrics) > 2000) $metrics = '{}';

$prevBest = (int)(db_one(
    'SELECT MAX(score) AS b FROM refactor_runs WHERE user_id = ? AND challenge_slug = ?',
    [(int)$me['id'], $slug]
)['b'] ?? 0);

db()->prepare(
    'INSERT INTO refactor_runs (user_id, challenge_slug, score, tests_passed, tests_total, metrics, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
)->execute([(int)$me['id'], $slug, $score, $testsPassed, $testsTotal, $metrics, now()]);

$best = max($prevBest, $score);
json_response(['ok' => true, 'score' => $score, 'best' => $best, 'improved' => $score > $prevBest]);

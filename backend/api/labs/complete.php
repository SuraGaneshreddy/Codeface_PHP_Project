<?php
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();
require_method('POST');
verify_csrf(true);

/* 🔒 Practice gate — even a hand-crafted request can't bank a lab early. */
$gateSolved = cf_solved_problems_count((int)$me['id']);
if ($gateSolved < cf_practice_gate()) {
    json_error('Pro Labs unlock after ' . cf_practice_gate() . " solved practice problems — you have {$gateSolved}/" . cf_practice_gate() . '.', 403);
}

$b = read_json_body();
$slug = (string)($b['slug'] ?? '');
$isAi = false;
if (!cf_get_lab($slug)) {
    /* AI lab: must be this user's own AND actually unlocked (no guessing ahead). */
    if (preg_match('/^ail(\d+)b(\d+)-/', $slug, $m) && (int)$m[1] === (int)$me['id']) {
        [$maxB] = cf_ai_labs_unlock(db(), (int)$me['id']);
        if ((int)$m[2] <= $maxB && cf_ai_lab((int)$me['id'], $slug)) $isAi = true;
    }
    if (!$isAi) json_error('Lab not found.', 404);
}

$sql = db_driver() === 'mysql'
    ? 'INSERT IGNORE INTO lab_progress (user_id, lab_slug, completed_at) VALUES (?, ?, ?)'
    : 'INSERT OR IGNORE INTO lab_progress (user_id, lab_slug, completed_at) VALUES (?, ?, ?)';
db()->prepare($sql)->execute([(int)$me['id'], $slug, now()]);

$done = (int)(db_one('SELECT COUNT(*) AS c FROM lab_progress WHERE user_id = ?', [(int)$me['id']])['c'] ?? 0);
[$maxB] = cf_ai_labs_unlock(db(), (int)$me['id']);
$total = count(cf_labs()) + $maxB * 10;

json_response(['ok' => true, 'completed' => true, 'labs_done' => $done, 'labs_total' => $total]);

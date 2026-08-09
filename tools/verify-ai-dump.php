<?php
/**
 * Developer tool — dumps 2 batches of AI-generated content (problems/labs/refactors)
 * for a probe user, twice, so verify-ai.js can machine-prove solvability + determinism.
 *
 * Usage:  php tools/verify-ai-dump.php
 * Writes: tools/_ai_dump_a.json + tools/_ai_dump_b.json (gitignore material)
 */
require __DIR__ . '/../backend/lib/emitters.php';
require __DIR__ . '/../backend/lib/aibank.php';

function dump_all(int $uid): array {
    $out = ['problems' => [], 'labs' => [], 'refactors' => []];
    foreach ([1, 2] as $b) {
        foreach (cf_ai_problems_specs($uid, $b) as $s) {
            $s['starters'] = cf_starters_all($s['fn'], $s['sig']);
            $out['problems'][] = $s;
        }
        foreach (cf_ai_labs_for($uid, $b) as $l) $out['labs'][] = $l;
        foreach (cf_ai_refactors_for($uid, $b) as $c) {
            $c['metrics_messy'] = cf_ai_metrics($c['files'][0]['content']);
            $c['metrics_fix']   = cf_ai_metrics($c['fix']);
            $out['refactors'][] = $c;
        }
    }
    return $out;
}
$a = dump_all(7);
$b = dump_all(7);
$ja = json_encode($a, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$jb = json_encode($b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/_ai_dump_a.json', $ja);
file_put_contents(__DIR__ . '/_ai_dump_b.json', $jb);
echo 'problems=', count($a['problems']), ' labs=', count($a['labs']), ' refactors=', count($a['refactors']), "\n";
echo 'determinism: ', ($ja === $jb ? "IDENTICAL ✓" : "MISMATCH ✗"), "\n";
exit($ja === $jb ? 0 : 1);

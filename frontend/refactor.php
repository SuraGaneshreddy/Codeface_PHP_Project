<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = current_user();

$page_title = 'Refactor Gym';
$active = 'refactor';

/* 🔒 Practice gate: Refactor Gym assumes you can solve small problems already. */
$gateSolved = cf_solved_problems_count($me ? (int)$me['id'] : null);
if ($gateSolved < cf_practice_gate()) {
    http_response_code(403);
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    $gateIcon = '🧹'; $gateSection = 'Refactor Gym';
    $gateTag = 'working-but-messy repos you must clean up without breaking behavior';
    $gateBackHref = 'index.php'; $gateBackLabel = 'Home';
    $gateLoginNext = 'refactor.php';
    require __DIR__ . '/../backend/partials/practice-gate.php';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

$best = [];
$doneMap = [];
if ($me) {
    foreach (db_all(
        'SELECT challenge_slug, MAX(score) AS b, MAX(tests_passed = tests_total AND tests_total > 0) AS d
           FROM refactor_runs WHERE user_id = ? GROUP BY challenge_slug',
        [(int)$me['id']]
    ) as $r) {
        $best[$r['challenge_slug']] = (int)$r['b'];
        if (!empty($r['d'])) $doneMap[$r['challenge_slug']] = true;
    }
}

/* 🤖 AI treadmill for the Refactor Gym (same rule as labs). */
$aiMaxBatch = 0; $aiCanonDone = false; $aiFlash = null;
$aiBatchView = 0; $aiChals = [];
if ($me) {
    [$aiMaxBatch, $aiCanonDone] = cf_ai_refactor_unlock(db(), (int)$me['id']);
    $seenKey = 'ai_rf_seen_batch';
    $seen = (int)($_SESSION[$seenKey] ?? 0); // first visit with sets already unlocked → flash them
    if ($aiMaxBatch > $seen) $aiFlash = $aiMaxBatch;
    $_SESSION[$seenKey] = $aiMaxBatch;
    if ($aiMaxBatch > 0) {
        $aiBatchView = (int)($_GET['aiset'] ?? $aiMaxBatch);
        if ($aiBatchView < 1 || $aiBatchView > $aiMaxBatch) $aiBatchView = $aiMaxBatch;
        $aiChals = cf_ai_refactors_for((int)$me['id'], $aiBatchView);
    }
}

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Refactor Gym — code maintenance, the real job</h1>
      <p class="page-sub">
        In industry, ~80% of engineering time goes into <em>reading and cleaning someone else's code</em>.
        Each repo below <strong>works but is a mess</strong>: improve complexity, duplication and naming
        <strong>without breaking the safety tests</strong>. Score rewards real cleanup, not rewrites.
      </p>
    </div>
  </div>

  <div class="track-grid">
    <?php foreach (cf_refactors() as $c): $b = $best[$c['slug']] ?? null; ?>
    <div class="card track-card">
      <div class="track-card-top">
        <span class="lab-kind lab-kind-refactor">🧹 refactor</span>
        <?php if ($b !== null): ?><span class="review-score <?= $b >= 85 ? 'ok' : ($b >= 60 ? 'mid' : 'bad') ?>"><?= $b ?>/100</span><?php endif; ?>
      </div>
      <h3 class="track-card-title"><?= e($c['title']) ?></h3>
      <p class="track-card-desc"><?= e($c['summary']) ?></p>
      <div class="track-card-meta">
        <span class="muted"><?= count($c['files']) ?> file<?= count($c['files']) > 1 ? 's' : '' ?> · <?= count($c['checks']) ?> safety tests</span>
      </div>
      <a class="btn <?= $b !== null ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="refactor-challenge.php?slug=<?= urlencode($c['slug']) ?>">
        <?= $b !== null ? 'Improve score' : 'Open repo' ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($aiFlash): ?>
    <div class="ai-banner" role="status">
      <span class="ai-banner-icon">🤖</span>
      <div><strong>You refactored the whole Gym!</strong> The Codeface AI just generated
        <strong>10 new messy repos</strong> for you — <em>set <?= (int)$aiFlash ?></em> below.
        Clean them all (tests green) to unlock the next set.</div>
    </div>
  <?php endif; ?>

  <?php if ($me): ?>
  <div class="ai-panel">
    <div class="ai-panel-head">
      <span class="ai-badge">🤖 AI Repo-Generator</span>
      <?php if (!$aiCanonDone): ?>
        <span class="ai-panel-note">Get every safety test green on <strong>all <?= count(cf_refactors()) ?> repos above</strong> and the AI will keep generating fresh messes for you — 10 at a time.</span>
      <?php else: ?>
        <span class="ai-panel-note">Every set you clean (tests green on all 10) unlocks the next. <?= $aiMaxBatch ?> set<?= $aiMaxBatch > 1 ? 's' : '' ?> generated so far.</span>
      <?php endif; ?>
    </div>
    <?php if ($aiMaxBatch > 0): ?>
    <div class="ai-batch-row">
      <?php for ($b = 1; $b <= $aiMaxBatch; $b++): ?>
        <a class="ai-batch-chip <?= $b === $aiBatchView ? 'ai-batch-done' : '' ?>" href="refactor.php?aiset=<?= $b ?>#ai-refactors">set <?= $b ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($aiChals): ?>
  <h2 id="ai-refactors" class="ai-section-title">🤖 AI-generated repos · set <?= $aiBatchView ?> <span class="muted">(made just for you)</span></h2>
  <div class="track-grid">
    <?php foreach ($aiChals as $c): $b = $best[$c['slug']] ?? null; $doneC = !empty($doneMap[$c['slug']]); ?>
    <div class="card track-card ai-card">
      <div class="track-card-top">
        <span class="lab-kind lab-kind-refactor">🧹 refactor</span>
        <?php if ($b !== null): ?><span class="review-score <?= $b >= 85 ? 'ok' : ($b >= 60 ? 'mid' : 'bad') ?>"><?= $b ?>/100</span><?php endif; ?>
      </div>
      <h3 class="track-card-title"><?= e($c['title']) ?></h3>
      <p class="track-card-desc"><?= e($c['summary']) ?></p>
      <div class="track-card-meta">
        <span class="muted"><?= count($c['files']) ?> file<?= count($c['files']) > 1 ? 's' : '' ?> · <?= count($c['checks']) ?> safety tests<?= $doneC ? ' · ✓ green once' : '' ?></span>
      </div>
      <a class="btn <?= $b !== null ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="refactor-challenge.php?slug=<?= urlencode($c['slug']) ?>">
        <?= $b !== null ? 'Improve score' : 'Open repo' ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

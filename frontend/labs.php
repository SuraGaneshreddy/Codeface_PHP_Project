<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = current_user();

$page_title = 'Pro Labs';
$active = 'labs';

/* 🔒 Practice gate: Labs assume you can solve small problems already. */
$gateSolved = cf_solved_problems_count($me ? (int)$me['id'] : null);
if ($gateSolved < cf_practice_gate()) {
    http_response_code(403);
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    $gateIcon = '🧪'; $gateSection = 'Pro Labs';
    $gateTag = 'multi-file legacy codebases with planted bugs and published (readonly) APIs';
    $gateBackHref = 'index.php'; $gateBackLabel = 'Home';
    $gateLoginNext = 'labs.php';
    require __DIR__ . '/../backend/partials/practice-gate.php';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

$doneMap = [];
if ($me) {
    foreach (db_all('SELECT lab_slug FROM lab_progress WHERE user_id = ?', [(int)$me['id']]) as $r) {
        $doneMap[$r['lab_slug']] = true;
    }
}

/* 🤖 AI treadmill: canonical labs all done → batch 1 appears; finish a
 * batch of 10 and the next one is generated. Session marker lets us flash
 * the "new set" banner exactly once per unlock. */
$aiMaxBatch = 0; $aiCanonDone = false; $aiFlash = null;
$aiBatchView = 0; $aiLabs = [];
if ($me) {
    [$aiMaxBatch, $aiCanonDone] = cf_ai_labs_unlock(db(), (int)$me['id']);
    $seenKey = 'ai_labs_seen_batch';
    $seen = (int)($_SESSION[$seenKey] ?? 0); // first visit with sets already unlocked → flash them
    if ($aiMaxBatch > $seen) {
        $aiFlash = $aiMaxBatch;
    }
    $_SESSION[$seenKey] = $aiMaxBatch;
    if ($aiMaxBatch > 0) {
        $aiBatchView = (int)($_GET['aiset'] ?? $aiMaxBatch);
        if ($aiBatchView < 1 || $aiBatchView > $aiMaxBatch) $aiBatchView = $aiMaxBatch;
        $aiLabs = cf_ai_labs_for((int)$me['id'], $aiBatchView);
    }
}

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Pro Labs — real-world engineering environments</h1>
      <p class="page-sub">
        Not another <code>main()</code> file. Each lab drops you into a small multi-file codebase:
        <strong>debug legacy systems</strong> with planted bugs, or <strong>integrate against readonly APIs</strong> —
        all sandboxed in your browser, checked by behavioral tasks.
      </p>
    </div>
  </div>

  <div class="track-grid">
    <?php foreach (cf_labs() as $lab): $done = $me && !empty($doneMap[$lab['slug']]); ?>
    <div class="card track-card">
      <div class="track-card-top">
        <span class="lab-kind lab-kind-<?= e($lab['kind']) ?>"><?= $lab['kind'] === 'debug' ? '🐞 legacy debug' : '🔌 API integration' ?></span>
        <?php if ($done): ?><span class="solved-check">✓ complete</span><?php endif; ?>
      </div>
      <h3 class="track-card-title"><?= e($lab['title']) ?></h3>
      <p class="track-card-desc"><?= e($lab['summary']) ?></p>
      <div class="track-card-meta">
        <span class="badge <?= difficulty_badge_class($lab['difficulty']) ?>"><?= e($lab['difficulty']) ?></span>
        <span class="muted"><?= count($lab['files']) ?> files · <?= count($lab['tasks']) ?> tasks · ~<?= (int)$lab['minutes'] ?> min</span>
      </div>
      <a class="btn <?= $done ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="lab.php?slug=<?= urlencode($lab['slug']) ?>">
        <?= $done ? 'Revisit lab' : 'Open environment' ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($aiFlash): ?>
    <div class="ai-banner" role="status">
      <span class="ai-banner-icon">🤖</span>
      <div><strong>You cleared every lab!</strong> The Codeface AI just built
        <strong>10 new multi-file environments</strong> for you — <em>set <?= (int)$aiFlash ?></em> below.
        Finish them and it will generate the next set.</div>
    </div>
  <?php endif; ?>

  <?php if ($me): ?>
  <div class="ai-panel">
    <div class="ai-panel-head">
      <span class="ai-badge">🤖 AI Lab-Builder</span>
      <?php if (!$aiCanonDone): ?>
        <span class="ai-panel-note">Complete <strong>all <?= count(cf_labs()) ?> labs above</strong> and the AI will start building fresh environments just for you — 10 at a time, forever.</span>
      <?php else: ?>
        <span class="ai-panel-note">Every set you finish unlocks the next one. <?= $aiMaxBatch ?> set<?= $aiMaxBatch > 1 ? 's' : '' ?> generated so far.</span>
      <?php endif; ?>
    </div>
    <?php if ($aiMaxBatch > 0): ?>
    <div class="ai-batch-row">
      <?php for ($b = 1; $b <= $aiMaxBatch; $b++): ?>
        <a class="ai-batch-chip <?= $b === $aiBatchView ? 'ai-batch-done' : '' ?>" href="labs.php?aiset=<?= $b ?>#ai-labs">set <?= $b ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($aiLabs): ?>
  <h2 id="ai-labs" class="ai-section-title">🤖 AI-built labs · set <?= $aiBatchView ?> <span class="muted">(made just for you)</span></h2>
  <div class="track-grid">
    <?php foreach ($aiLabs as $lab): $done = !empty($doneMap[$lab['slug']]); ?>
    <div class="card track-card ai-card">
      <div class="track-card-top">
        <span class="lab-kind lab-kind-<?= e($lab['kind']) ?>"><?= $lab['kind'] === 'debug' ? '🐞 legacy debug' : '🔌 API integration' ?></span>
        <?php if ($done): ?><span class="solved-check">✓ complete</span><?php endif; ?>
      </div>
      <h3 class="track-card-title"><?= e($lab['title']) ?></h3>
      <p class="track-card-desc"><?= e($lab['summary']) ?></p>
      <div class="track-card-meta">
        <span class="badge <?= difficulty_badge_class($lab['difficulty']) ?>"><?= e($lab['difficulty']) ?></span>
        <span class="muted"><?= count($lab['files']) ?> files · <?= count($lab['tasks']) ?> tasks · ~<?= (int)$lab['minutes'] ?> min</span>
      </div>
      <a class="btn <?= $done ? 'btn-outline' : 'btn-primary' ?> btn-sm" href="lab.php?slug=<?= urlencode($lab['slug']) ?>">
        <?= $done ? 'Revisit lab' : 'Open environment' ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

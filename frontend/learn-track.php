<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = current_user();

$lang = (string)($_GET['l'] ?? '');
$meta = cf_learn_tracks_meta($lang);
if (!$meta) {
    http_response_code(404);
    $page_title = 'Track not found';
    $active = 'learn';
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Track not found</h1><p><a href="learn.php">Back to all languages</a></p></div></div>';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

/* all lessons of the track, grouped per level */
$lessons = db_all('SELECT id, position, title, minutes FROM learn_lessons WHERE track = ? ORDER BY position ASC', [$lang]);
$doneIds = [];
if ($me) {
    foreach (db_all(
        'SELECT p.lesson_id FROM learn_progress p JOIN learn_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? AND l.track = ?',
        [(int)$me['id'], $lang]
    ) as $r) $doneIds[(int)$r['lesson_id']] = true;
}
$doneCount = count($doneIds);
$totalMin = array_sum(array_map(function ($l) { return (int)$l['minutes']; }, $lessons));
$card = cf_learn_card($lang);
$levels = cf_learn_levels();

/* per-level aggregates */
$stats = [];
foreach ($levels as $slug => $lv) {
    $stats[$slug] = ['total' => 0, 'done' => 0, 'mins' => 0, 'firstPos' => null];
}
foreach ($lessons as $l) {
    $slug = cf_learn_level_of_position((int)$l['position']);
    $stats[$slug]['total']++;
    $stats[$slug]['mins'] += (int)$l['minutes'];
    if ($stats[$slug]['firstPos'] === null) $stats[$slug]['firstPos'] = (int)$l['position'];
    if (isset($doneIds[(int)$l['id']])) $stats[$slug]['done']++;
}

$page_title = 'Learn ' . $meta['name'];
$active = 'learn';
require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <nav class="crumbs"><a href="learn.php">← All languages</a></nav>
  <div class="page-head">
    <div>
      <h1><?= e($meta['name']) ?> <span class="badge badge-track"><?= e($card['level']) ?></span></h1>
      <p class="page-sub"><?= e($meta['blurb']) ?> <?= e($card['hook']) ?> · <?= count($lessons) ?> lessons across 4 levels · about <?= $totalMin ?> minutes<?= cf_runner_available($lang) ? ' · fully runnable in your browser' : '' ?></p>
    </div>
    <?php if ($doneCount > 0): ?>
    <div class="progress-ring"><span><?= $doneCount ?><em>/<?= count($lessons) ?></em></span></div>
    <?php endif; ?>
  </div>

  <div class="level-grid">
    <?php
    $uid = $me ? (int)$me['id'] : null;
    $order = cf_learn_level_order();
    foreach ($levels as $slug => $lv):
        $st = $stats[$slug];
        $pct = $st['total'] > 0 ? round(100 * $st['done'] / $st['total']) : 0;
        $complete = $st['total'] > 0 && $st['done'] === $st['total'];
        $unlocked = cf_learn_level_unlocked($lang, $slug, $uid);
        $i = array_search($slug, $order, true);
        $prevName = $i > 0 ? $levels[$order[$i - 1]]['name'] : '';
    ?>
    <?php if ($unlocked): ?>
    <a class="card level-card<?= $complete ? ' level-complete' : '' ?>" href="learn-level.php?l=<?= urlencode($lang) ?>&amp;level=<?= e($slug) ?>">
      <div class="level-icon"><?= e($lv['icon']) ?></div>
      <div class="level-body">
        <div class="level-title-row">
          <h3><?= e($lv['name']) ?></h3>
          <?php if ($complete): ?><span class="solved-check">✓ complete</span><?php endif; ?>
        </div>
        <p class="muted level-blurb"><?= e($lv['blurb']) ?></p>
        <div class="level-meta">
          <span><?= $st['total'] ?> lessons · ~<?= $st['mins'] ?> min</span>
          <?php if ($st['done'] > 0 && !$complete): ?><span class="ok-text"><?= $st['done'] ?>/<?= $st['total'] ?> done</span><?php endif; ?>
        </div>
        <div class="progress" aria-hidden="true"><span style="width:<?= $pct ?>%"></span></div>
      </div>
      <span class="lesson-go">→</span>
    </a>
    <?php else: ?>
    <div class="card level-card locked" aria-disabled="true">
      <div class="level-icon">🔒</div>
      <div class="level-body">
        <div class="level-title-row">
          <h3><?= e($lv['icon']) ?> <?= e($lv['name']) ?></h3>
          <span class="lock-badge">locked</span>
        </div>
        <p class="muted level-blurb"><?= e($lv['blurb']) ?></p>
        <div class="level-meta">
          <span><?= $st['total'] ?> lessons · ~<?= $st['mins'] ?> min</span>
          <span class="lock-note">Complete <?= e($prevName) ?> to unlock</span>
        </div>
        <div class="progress" aria-hidden="true"><span style="width:0%"></span></div>
      </div>
      <span class="lesson-go">🔒</span>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!$me): ?>
  <p class="muted" style="margin-top:1rem"><a href="login.php?next=learn-track.php%3Fl%3D<?= urlencode($lang) ?>">Log in</a> to track your progress across lessons — and to unlock 🌿 Intermediate, 🌳 Advance and 🏆 Pro as you finish each level.</p>
  <?php else: ?>
  <p class="muted" style="margin-top:1rem">Levels unlock in order as you finish them: 🌱 → 🌿 → 🌳 → 🏆.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

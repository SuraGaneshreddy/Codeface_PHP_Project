<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = current_user();

$page_title = 'Learn';
$active = 'learn';

$counts = [];
foreach (db_all('SELECT track, COUNT(*) AS c FROM learn_lessons GROUP BY track') as $r) {
    $counts[$r['track']] = (int)$r['c'];
}
$done = [];
if ($me) {
    foreach (db_all(
        'SELECT l.track, COUNT(*) AS c FROM learn_progress p JOIN learn_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? GROUP BY l.track',
        [(int)$me['id']]
    ) as $r) $done[$r['track']] = (int)$r['c'];
}
$totalLessons = array_sum($counts);

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Learn a language</h1>
      <p class="page-sub">
        <?= $totalLessons ?> bite-size lessons across <?= count(cf_learn_tracks_meta()) ?> languages — pick a language, then climb
        <strong>🌱 Beginner → 🌿 Intermediate → 🌳 Advance → 🏆 Pro</strong> (4 lessons per level; each level unlocks when you finish the one before it). Every lesson taught through real-world scenarios, never foo/bar.
      </p>
    </div>
  </div>

  <div class="track-grid">
    <?php foreach (cf_learn_tracks_meta() as $id => $m):
        $card = cf_learn_card($id);
        $total = $counts[$id] ?? 0;
        $finished = $done[$id] ?? 0;
        $pct = $total > 0 ? round(100 * $finished / $total) : 0;
        $runnable = cf_runner_available($id);
    ?>
    <a class="card track-card" href="learn-track.php?l=<?= urlencode($id) ?>">
      <div class="track-head">
        <h3><?= e($m['name']) ?></h3>
        <span class="badge badge-track"><?= e($card['level']) ?></span>
      </div>
      <p class="muted track-blurb"><?= e($m['blurb']) ?></p>
      <p class="track-hook"><?= e($card['hook']) ?></p>
      <div class="track-foot">
        <span class="mini-meta"><?= $total ?> lessons<?= $runnable ? ' · runnable in browser' : '' ?></span>
        <?php if ($finished > 0): ?>
          <span class="mini-meta ok-text"><?= $finished ?>/<?= $total ?> done</span>
        <?php endif; ?>
      </div>
      <div class="progress" aria-hidden="true"><span style="width:<?= $pct ?>%"></span></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

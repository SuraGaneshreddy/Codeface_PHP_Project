<?php
require __DIR__ . '/lib/bootstrap.php';
$me = current_user();

$lang = (string)($_GET['l'] ?? '');
$meta = cf_learn_tracks_meta($lang);
if (!$meta) {
    http_response_code(404);
    $page_title = 'Track not found';
    $active = 'learn';
    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Track not found</h1><p><a href="learn.php">Back to all tracks</a></p></div></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$lessons = db_all('SELECT * FROM learn_lessons WHERE track = ? ORDER BY position ASC', [$lang]);
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

// resolve practice links
$problemTitles = [];
foreach (db_all('SELECT slug, title FROM problems') as $p) $problemTitles[$p['slug']] = $p['title'];

$page_title = 'Learn ' . $meta['name'];
$active = 'learn';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container">
  <nav class="crumbs"><a href="learn.php">← All tracks</a></nav>
  <div class="page-head">
    <div>
      <h1><?= e($meta['name']) ?> <span class="badge badge-track"><?= e($card['level']) ?></span></h1>
      <p class="page-sub"><?= e($meta['blurb']) ?> <?= e($card['hook']) ?> · <?= count($lessons) ?> lessons · about <?= $totalMin ?> minutes<?= cf_runner_available($lang) ? ' · fully runnable in your browser' : '' ?></p>
    </div>
    <?php if ($doneCount > 0): ?>
    <div class="progress-ring"><span><?= $doneCount ?><em>/<?= count($lessons) ?></em></span></div>
    <?php endif; ?>
  </div>

  <div class="lesson-list">
    <?php foreach ($lessons as $l):
        $isDone = isset($doneIds[(int)$l['id']]);
        $prob = $l['problem_slug'] !== '' ? ($problemTitles[$l['problem_slug']] ?? null) : null;
    ?>
    <a class="card lesson-row<?= $isDone ? ' done' : '' ?>" href="learn-lesson.php?l=<?= urlencode($lang) ?>&amp;n=<?= (int)$l['position'] ?>">
      <span class="lesson-bubble <?= $isDone ? 'bubble-done' : '' ?>"><?= $isDone ? '✓' : (int)$l['position'] ?></span>
      <span class="lesson-title-wrap">
        <span class="lesson-title"><?= e($l['title']) ?></span>
        <span class="lesson-meta">
          <?= (int)$l['minutes'] ?> min
          <?php if ($l['try_code'] !== ''): ?> · <span class="ok-text">runnable</span><?php endif; ?>
          <?php if ($prob): ?> · practice: <?= e($prob) ?><?php endif; ?>
        </span>
      </span>
      <span class="lesson-go">→</span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$me): ?>
  <p class="muted" style="margin-top:1rem"><a href="login.php?next=learn-track.php%3Fl%3D<?= urlencode($lang) ?>">Log in</a> to track your progress across lessons.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

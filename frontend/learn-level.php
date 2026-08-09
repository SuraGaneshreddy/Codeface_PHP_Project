<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = current_user();

$lang  = (string)($_GET['l'] ?? '');
$level = (string)($_GET['level'] ?? '');
$meta  = cf_learn_tracks_meta($lang);
$levels = cf_learn_levels();
$lv    = $levels[$level] ?? null;

if (!$meta || !$lv) {
    http_response_code(404);
    $page_title = 'Not found';
    $active = 'learn';
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Level not found</h1><p><a href="learn.php">Back to all languages</a></p></div></div>';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

/* Hard level locking: server-side gate — a level opens only when the previous
   level is fully complete for THIS user (guests: everything past Beginner is locked). */
if (!cf_learn_level_unlocked($lang, $level, $me ? (int)$me['id'] : null)) {
    http_response_code(403);
    $page_title = $lv['name'] . ' locked — Learn ' . $meta['name'];
    $active = 'learn';
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    require __DIR__ . '/../backend/partials/learn-locked.php';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

[$from, $to] = $lv['range'];
$lessons = db_all(
    'SELECT * FROM learn_lessons WHERE track = ? AND position BETWEEN ? AND ? ORDER BY position ASC',
    [$lang, $from, $to]
);
$doneIds = [];
if ($me) {
    foreach (db_all(
        'SELECT p.lesson_id FROM learn_progress p JOIN learn_lessons l ON l.id = p.lesson_id
         WHERE p.user_id = ? AND l.track = ? AND l.position BETWEEN ? AND ?',
        [(int)$me['id'], $lang, $from, $to]
    ) as $r) $doneIds[(int)$r['lesson_id']] = true;
}
$doneCount = count($doneIds);
$totalMin = array_sum(array_map(function ($l) { return (int)$l['minutes']; }, $lessons));
$complete = count($lessons) > 0 && $doneCount === count($lessons);

// resolve practice links
$problemTitles = [];
foreach (db_all('SELECT slug, title FROM problems') as $p) $problemTitles[$p['slug']] = $p['title'];

$page_title = "{$lv['name']} " . $meta['name'];
$active = 'learn';
require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <nav class="crumbs"><a href="learn.php">← All languages</a> <span class="crumb-sep">/</span> <a href="learn-track.php?l=<?= urlencode($lang) ?>"><?= e($meta['name']) ?></a></nav>
  <div class="page-head">
    <div>
      <h1><?= e($lv['icon']) ?> <?= e($lv['name']) ?> <span class="muted level-sub">· <?= e($meta['name']) ?></span></h1>
      <p class="page-sub"><?= e($lv['blurb']) ?> <?= count($lessons) ?> lessons · about <?= $totalMin ?> minutes<?= cf_runner_available($lang) ? ' · runnable in your browser' : '' ?></p>
    </div>
    <?php if ($doneCount > 0): ?>
    <div class="progress-ring"><span><?= $doneCount ?><em>/<?= count($lessons) ?></em></span></div>
    <?php endif; ?>
  </div>

  <?php if ($complete): ?>
  <div class="card level-banner">🏆 Level complete — go claim the next one: <a href="learn-track.php?l=<?= urlencode($lang) ?>">back to <?= e($meta['name']) ?> levels</a>.</div>
  <?php endif; ?>

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
          <?php if ($l['try_code'] !== '' && cf_runner_available($lang)): ?> · <span class="ok-text">runnable</span><?php elseif ($l['try_code'] !== ''): ?> · has practice prompt<?php endif; ?>
          <?php if ($prob): ?> · practice: <?= e($prob) ?><?php endif; ?>
        </span>
      </span>
      <span class="lesson-go">→</span>
    </a>
    <?php endforeach; ?>
  </div>

  <p class="level-switch muted">Other levels in this track:
    <?php
    $otherLinks = [];
    foreach ($levels as $slug => $other) {
        if ($slug === $level) continue;
        if (cf_learn_level_unlocked($lang, $slug, $me ? (int)$me['id'] : null)) {
            $otherLinks[] = '<a href="learn-level.php?l=' . urlencode($lang) . '&amp;level=' . e($slug) . '">' . e($other['icon']) . ' ' . e($other['name']) . '</a>';
        } else {
            $otherLinks[] = '<span class="level-locked-note" title="Complete the previous level to unlock">🔒 ' . e($other['name']) . '</span>';
        }
    }
    echo implode(' · ', $otherLinks);
    ?>
  </p>

  <?php if (!$me): ?>
  <p class="muted" style="margin-top:1rem"><a href="login.php?next=learn-level.php%3Fl%3D<?= urlencode($lang) ?>%26level%3D<?= urlencode($level) ?>">Log in</a> to track your progress.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

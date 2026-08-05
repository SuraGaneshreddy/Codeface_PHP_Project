<?php
require __DIR__ . '/lib/bootstrap.php';
$me = current_user();

$lang = (string)($_GET['l'] ?? '');
$meta = cf_learn_tracks_meta($lang);
$n = (int)($_GET['n'] ?? 1);

$lesson = $meta ? db_one('SELECT * FROM learn_lessons WHERE track = ? AND position = ?', [$lang, $n]) : null;
if (!$meta || !$lesson) {
    http_response_code(404);
    $page_title = 'Lesson not found';
    $active = 'learn';
    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Lesson not found</h1><p><a href="learn.php">Back to all tracks</a></p></div></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$hasPrev = (bool)db_one('SELECT id FROM learn_lessons WHERE track = ? AND position = ?', [$lang, $n - 1]);
$hasNext = (bool)db_one('SELECT id FROM learn_lessons WHERE track = ? AND position = ?', [$lang, $n + 1]);
$isDone = $me ? (bool)db_one('SELECT lesson_id FROM learn_progress WHERE user_id = ? AND lesson_id = ?', [(int)$me['id'], $lesson['id']]) : false;
$problem = $lesson['problem_slug'] !== '' ? db_one('SELECT slug, title, difficulty FROM problems WHERE slug = ?', [$lesson['problem_slug']]) : null;

$runner = (string)($meta['runner'] ?? '');
$payload = [
    'lessonId' => (int)$lesson['id'],
    'lang'     => $lang,
    'monaco'   => $meta['monaco'],
    'runner'   => $runner,
    'runnable' => $lesson['try_code'] !== '' && cf_runner_available($lang),
    'done'     => $isDone,
    'loggedIn' => (bool)$me,
];

$page_title = $lesson['title'] . ' — Learn ' . $meta['name'];
$active = 'learn';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<script id="lessonData" type="application/json"><?= json_encode($payload + ['try' => $lesson['try_code']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="container lesson-wrap">
  <nav class="crumbs">
    <a href="learn-track.php?l=<?= urlencode($lang) ?>">← <?= e($meta['name']) ?> track</a>
    <span class="crumbs-nav">
      <?php if ($hasPrev): ?><a href="learn-lesson.php?l=<?= urlencode($lang) ?>&amp;n=<?= $n - 1 ?>">‹ Prev</a><?php endif; ?>
      <?php if ($hasNext): ?><a href="learn-lesson.php?l=<?= urlencode($lang) ?>&amp;n=<?= $n + 1 ?>">Next ›</a><?php endif; ?>
    </span>
  </nav>

  <div class="card lesson-card">
    <div class="lesson-card-head">
      <div>
        <h1><?= e($lesson['title']) ?></h1>
        <p class="muted">Lesson <?= (int)$lesson['position'] ?> · <?= (int)$lesson['minutes'] ?> min · <?= e($meta['name']) ?></p>
      </div>
      <span class="lesson-status-pill <?= $isDone ? 'done-pill' : '' ?>" id="statusPill"><?= $isDone ? '✓ completed' : 'in progress' ?></span>
    </div>

    <div class="desc-body lesson-concept"><?= allow_html($lesson['concept']) ?></div>

    <?php if ($lesson['example_code'] !== ''): ?>
    <h4 class="tests-preview-title">How it looks in <?= e($meta['name']) ?></h4>
    <pre class="code-block"><code><?= e($lesson['example_code']) ?></code></pre>
    <?php endif; ?>

    <?php if ($lesson['example_output'] !== ''): ?>
    <h4 class="tests-preview-title">What it prints</h4>
    <pre class="output-block"><code><?= e($lesson['example_output']) ?></code></pre>
    <?php endif; ?>

    <?php if ($lesson['try_code'] !== ''): ?>
      <?php if (cf_runner_available($lang)): ?>
      <div class="try-block">
        <div class="try-head">
          <h4>Try it yourself — edit and run, right here</h4>
          <button class="btn btn-outline btn-sm" id="btnTryRun" type="button">▶ Run</button>
        </div>
        <div id="tryEditor" class="editor-host try-editor"></div>
        <pre class="console-panel" id="tryOut">// output appears here</pre>
      </div>
      <?php else: ?>
      <div class="try-block try-note">
        <h4>Try it yourself</h4>
        <p class="muted">In-browser execution is available for JavaScript, TypeScript and Python (no safe vanilla-PHP way to run <?= e($meta['name']) ?> on a shared host). Copy the example into your local <?= e($meta['name']) ?> toolchain — or switch to the JS version of this lesson to run it in the page: same ideas, same structure.</p>
      </div>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($problem): ?>
    <div class="practice-callout">
      <span>🥋 <strong>Now make it muscle memory:</strong> apply this lesson on a practice problem.</span>
      <a class="btn btn-primary btn-sm" href="problem.php?slug=<?= urlencode($problem['slug']) ?>">Solve: <?= e($problem['title']) ?> →</a>
    </div>
    <?php endif; ?>

    <div class="lesson-actions">
      <?php if ($me): ?>
      <button class="btn <?= $isDone ? 'btn-ghost' : 'btn-primary' ?>" id="btnComplete" type="button">
        <?= $isDone ? '✓ Completed — undo' : 'Mark lesson complete' ?>
      </button>
      <?php else: ?>
      <a class="btn btn-outline" href="login.php?next=learn-lesson.php%3Fl%3D<?= urlencode($lang) ?>%26n%3D<?= $n ?>">Log in to track progress</a>
      <?php endif; ?>
      <?php if ($hasNext): ?><a class="btn btn-outline" href="learn-lesson.php?l=<?= urlencode($lang) ?>&amp;n=<?= $n + 1 ?>">Next lesson →</a><?php endif; ?>
    </div>
  </div>
</div>

<?php
$page_scripts = ['assets/js/runner.js', 'assets/js/editor.js', 'assets/js/learn-lesson.js'];
require __DIR__ . '/partials/footer.php';
?>

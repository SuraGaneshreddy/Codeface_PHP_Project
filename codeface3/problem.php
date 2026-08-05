<?php
require __DIR__ . '/lib/bootstrap.php';
$me = require_login();

$slug = (string)($_GET['slug'] ?? '');
$problem = db_one('SELECT * FROM problems WHERE slug = ?', [$slug]);
if (!$problem) {
    http_response_code(404);
    $page_title = 'Problem not found';
    $active = 'problems';
    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Problem not found</h1><p>It may have been removed. <a href="problems.php">Back to the problem list</a>.</p></div></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}

$tests    = json_decode($problem['tests_json'], true) ?: [];
$starters = json_decode($problem['starters_json'] ?? '', true);
if (!is_array($starters) || !$starters) {
    $starters = ['javascript' => $problem['starter_js']]; // legacy row fallback
}
$visible = array_values(array_filter($tests, function ($t) { return !empty($t['visible']); }));
$hiddenCount = count($tests) - count($visible);

$solvedBefore = (bool)db_one(
    "SELECT id FROM submissions WHERE user_id = ? AND problem_id = ? AND status = 'pass' LIMIT 1",
    [$me['id'], $problem['id']]
);

$next = db_one('SELECT slug, title FROM problems WHERE id > ? ORDER BY id ASC LIMIT 1', [$problem['id']]);
$prev = db_one('SELECT slug, title FROM problems WHERE id < ? ORDER BY id DESC LIMIT 1', [$problem['id']]);

$catNames = [
    'arrays' => 'Arrays', 'strings' => 'Strings', 'math' => 'Math', 'hashmap' => 'Hash Maps',
    'twoptr' => 'Two Pointers', 'stack' => 'Stack & Queue', 'search' => 'Search & Sort', 'dp' => 'Dynamic Programming',
    'greedy' => 'Greedy', 'bits' => 'Bit Magic', 'matrix' => 'Matrix', 'realworld' => 'Real World',
];
$category = (string)($problem['category'] ?? '');
$moreInCat = $category !== ''
    ? db_all('SELECT slug, title FROM problems WHERE category = ? AND slug != ? ORDER BY id ASC LIMIT 3', [$category, $slug])
    : [];

$languages = [];
foreach (cf_languages() as $id => $m) {
    $languages[] = ['id' => $id, 'name' => $m['name'], 'monaco' => $m['monaco'], 'runner' => $m['runner']];
}
$payload = [
    'problemId'    => (int)$problem['id'],
    'slug'         => $problem['slug'],
    'category'     => $category,
    'functionName' => $problem['function_name'],
    'fnNames'      => cf_fn_names_all($problem['function_name']),
    'starters'     => $starters,
    'tests'        => $tests,
    'solution'     => $problem['solution_js'],
    'languages'    => $languages,
];

$page_title = $problem['title'];
$active = 'problems';

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<script id="problemData" type="application/json"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>

<div class="container wide">
  <nav class="crumbs">
    <a href="problems.php">← Practice</a>
    <span class="crumbs-nav">
      <?php if ($prev): ?><a href="problem.php?slug=<?= urlencode($prev['slug']) ?>" title="<?= e($prev['title']) ?>">‹ Prev</a><?php endif; ?>
      <?php if ($next): ?><a href="problem.php?slug=<?= urlencode($next['slug']) ?>" title="<?= e($next['title']) ?>">Next ›</a><?php endif; ?>
    </span>
  </nav>

  <div class="split">
    <section class="pane pane-left">
      <div class="pane-scroll problem-desc">
        <div class="problem-title-row">
          <h1><?= e($problem['title']) ?></h1>
          <?php if ($solvedBefore): ?><span class="solved-check">✓ solved</span><?php endif; ?>
        </div>
        <div class="problem-meta-row">
          <span class="badge <?= difficulty_badge_class($problem['difficulty']) ?>"><?= e($problem['difficulty']) ?></span>
          <span class="pts"><?= (int)$problem['points'] ?> pts</span>
          <?php if ($category !== '' && isset($catNames[$category])): ?>
          <a class="tag tag-link" href="problems.php?cat=<?= urlencode($category) ?>"><?= e($catNames[$category]) ?></a>
          <?php endif; ?>
        </div>
        <div class="desc-body"><?= allow_html($problem['description']) ?></div>

        <h4 class="tests-preview-title">Visible tests</h4>
        <ul class="tests-preview">
          <?php foreach ($visible as $t): ?>
          <li><code><?= e($problem['function_name']) ?>(<?= e(implode(', ', array_map(function ($a) { return json_encode($a, JSON_UNESCAPED_SLASHES); }, $t['args']))) ?>)</code>
              → <code><?= e(json_encode($t['expected'], JSON_UNESCAPED_SLASHES)) ?></code></li>
          <?php endforeach; ?>
          <?php if ($hiddenCount > 0): ?><li class="muted">+ <?= $hiddenCount ?> hidden edge-case test<?= $hiddenCount > 1 ? 's' : '' ?> run on submit</li><?php endif; ?>
        </ul>

        <?php if ($moreInCat): ?>
        <div class="more-in-cat">
          <h4>More in <?= e($catNames[$category] ?? 'this category') ?></h4>
          <div class="badge-cloud">
            <?php foreach ($moreInCat as $m): ?>
            <a class="tag tag-link" href="problem.php?slug=<?= urlencode($m['slug']) ?>"><?= e($m['title']) ?></a>
            <?php endforeach; ?>
            <a class="tag tag-link" href="problems.php?cat=<?= urlencode($category) ?>">all →</a>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <section class="pane pane-right">
      <div class="editor-toolbar">
        <select class="input input-sm lang-select" id="langSel" aria-label="Language"></select>
        <span class="toolbar-note" id="runnerNote"></span>
        <span class="spacer"></span>
        <button class="btn btn-ghost btn-sm" id="btnRef" type="button">Reference</button>
        <button class="btn btn-ghost btn-sm" id="btnReset" type="button">Reset</button>
        <button class="btn btn-outline btn-sm" id="btnRun" type="button">▶ Run tests</button>
        <button class="btn btn-primary btn-sm" id="btnSubmit" type="button">Submit</button>
      </div>
      <div class="ref-panel hidden" id="refPanel">
        <div class="ref-head">Reference solution <span class="muted">(JavaScript — the language the judge runs)</span></div>
        <pre><code id="refCode"></code></pre>
      </div>
      <div id="editorHost" class="editor-host"></div>
      <div class="results-bar" id="resultsBar">
        <span id="resultsSummary" class="muted">Run the tests to see results.</span>
        <span id="resultsTime" class="muted"></span>
      </div>
      <div id="results" class="results"></div>
    </section>
  </div>
</div>

<?php
$page_scripts = ['assets/js/editor.js', 'assets/js/runner.js', 'assets/js/problem.js'];
require __DIR__ . '/partials/footer.php';
?>

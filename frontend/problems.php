<?php
require __DIR__ . '/../backend/lib/bootstrap.php';

$me = current_user();
$page_title = 'Practice problems';
$active = 'problems';

$difficulty = strtolower(trim((string)($_GET['difficulty'] ?? '')));
$q          = trim((string)($_GET['q'] ?? ''));
$cat        = strtolower(trim((string)($_GET['cat'] ?? '')));
if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) $difficulty = '';

$catLabels = [
    'arrays' => 'Arrays', 'strings' => 'Strings', 'math' => 'Math & Logic',
    'hashmap' => 'Hash Maps', 'twoptr' => 'Two Pointers', 'stack' => 'Stacks',
    'search' => 'Search', 'dp' => 'Dynamic Programming', 'greedy' => 'Greedy',
    'bits' => 'Bit Manipulation', 'matrix' => 'Matrix', 'realworld' => 'Real-World Mini-Apps',
    'ai' => '🤖 AI-made',
];

/* 🤖 AI treadmill: when this user has solved every problem they can see
 * (canonical set + their newest AI batch), generate the next batch of 10. */
$aiFlash = null;
if ($me) {
    $aiFlash = cf_ai_problems_tick(db(), (int)$me['id']);
}

/* Visibility: canonical problems (ai_user_id IS NULL) + this user's AI-made rows. */
$ownSql = '(p.ai_user_id IS NULL' . ($me ? ' OR p.ai_user_id = ?' : '') . ')';
$ownParams = $me ? [(int)$me['id']] : [];

$where = [$ownSql];
$params = $ownParams;
if ($difficulty !== '') { $where[] = 'p.difficulty = ?'; $params[] = $difficulty; }
if ($cat !== '' && isset($catLabels[$cat])) { $where[] = 'p.category = ?'; $params[] = $cat; }
if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.tags LIKE ? OR p.category LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql = 'SELECT p.id, p.slug, p.title, p.difficulty, p.points, p.tags, p.category, p.ai_user_id,
               (SELECT COUNT(*) FROM submissions s WHERE s.problem_id = p.id) AS attempts
        FROM problems p'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY p.id ASC';
$problems = db_all($sql, $params);

$solved = [];
if ($me) {
    foreach (db_all("SELECT DISTINCT problem_id FROM submissions WHERE user_id = ? AND status = 'pass'", [$me['id']]) as $r) {
        $solved[(int)$r['problem_id']] = true;
    }
}
$counts = ['easy' => 0, 'medium' => 0, 'hard' => 0];
foreach (db_all("SELECT difficulty, COUNT(*) AS c FROM problems p WHERE $ownSql GROUP BY difficulty", $ownParams) as $r) $counts[$r['difficulty']] = (int)$r['c'];
$catCounts = [];
foreach (db_all("SELECT category, COUNT(*) AS c FROM problems p WHERE $ownSql GROUP BY category", $ownParams) as $r) $catCounts[$r['category']] = (int)$r['c'];

/* Group owned AI rows into batches for the highlight panel. */
$aiBatches = [];
if ($me) {
    foreach (cf_ai_problems_owned(db(), (int)$me['id']) as $row) {
        if (preg_match('/^aip\d+b(\d+)-/', $row['slug'], $m)) {
            $b = (int)$m[1];
            if (!isset($aiBatches[$b])) $aiBatches[$b] = ['total' => 0, 'solved' => 0];
            $aiBatches[$b]['total']++;
            if (!empty($row['solved'])) $aiBatches[$b]['solved']++;
        }
    }
    ksort($aiBatches);
}
$solvedCount = count($solved);
$total = max(1, array_sum($counts));

/* Helpers to preserve each other's filters in chip links. */
$mkq = function (array $over) use ($difficulty, $q, $cat): string {
    $p = array_filter(array_merge(['difficulty' => $difficulty, 'q' => $q, 'cat' => $cat], $over));
    return $p ? '?' . http_build_query($p) : '';
};

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Practice</h1>
      <p class="page-sub">Pick a problem, write code, get instant feedback — in any of <?= count(cf_languages()) ?> languages.
      <?php if ($me): ?>You've solved <strong><?= $solvedCount ?></strong> of <?= $total ?>.<?php endif; ?></p>
    </div>
    <?php if ($me): ?>
    <div class="progress-ring" title="<?= $solvedCount ?>/<?= $total ?> solved">
      <span><?= $solvedCount ?><em>/<?= $total ?></em></span>
    </div>
    <?php endif; ?>
  </div>

  <form class="filter-bar" method="get" action="problems.php">
    <div class="chip-row">
      <a class="chip <?= $difficulty === '' ? 'chip-on' : '' ?>" href="problems.php<?= e($mkq(['difficulty' => null])) ?>">All (<?= $total ?>)</a>
      <?php foreach (['easy', 'medium', 'hard'] as $d): ?>
        <a class="chip chip-<?= $d ?> <?= $difficulty === $d ? 'chip-on' : '' ?>" href="problems.php<?= e($mkq(['difficulty' => $d])) ?>"><?= ucfirst($d) ?> (<?= $counts[$d] ?>)</a>
      <?php endforeach; ?>
    </div>
    <div class="chip-row">
      <a class="chip <?= $cat === '' ? 'chip-on' : '' ?>" href="problems.php<?= e($mkq(['cat' => null])) ?>">All topics</a>
      <?php foreach ($catLabels as $cid => $label): if (!isset($catCounts[$cid])) continue; ?>
        <a class="chip <?= $cat === $cid ? 'chip-on' : '' ?>" href="problems.php<?= e($mkq(['cat' => $cid])) ?>"><?= e($label) ?> (<?= $catCounts[$cid] ?>)</a>
      <?php endforeach; ?>
    </div>
    <div class="search-box">
      <input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or tag… (e.g. hashmap)">
      <?php if ($difficulty): ?><input type="hidden" name="difficulty" value="<?= e($difficulty) ?>"><?php endif; ?>
      <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
      <button class="btn btn-ghost" type="submit">Search</button>
    </div>
  </form>

  <?php if ($aiFlash): ?>
    <div class="ai-banner" role="status">
      <span class="ai-banner-icon">🤖</span>
      <div><strong>You finished every problem!</strong> The Codeface AI just minted
        <strong><?= (int)$aiFlash['count'] ?> new problems</strong> for you — meet
        <em>set <?= (int)$aiFlash['batch'] ?></em> below. It runs out when you do: finish them and it will make ten more.</div>
    </div>
  <?php endif; ?>

  <?php if ($me): ?>
  <div class="ai-panel">
    <div class="ai-panel-head">
      <span class="ai-badge">🤖 AI Problem-Setter</span>
      <?php if ($aiBatches): ?>
        <span class="ai-panel-note">Finish every problem and the AI writes your next set of 10 — forever.</span>
      <?php else: ?>
        <span class="ai-panel-note">Solve <strong>every</strong> problem on this page and the AI will mint 10 more just for you — then again for each set you finish.</span>
      <?php endif; ?>
    </div>
    <?php if ($aiBatches): ?>
    <div class="ai-batch-row">
      <?php foreach ($aiBatches as $b => $st): $doneAll = $st['solved'] >= $st['total']; ?>
        <a class="ai-batch-chip <?= $doneAll ? 'ai-batch-done' : '' ?>"
           href="problems.php?cat=ai"
           title="<?= $doneAll ? 'Set complete — well done!' : 'Keep going…' ?>">
          set <?= $b ?> · <?= $st['solved'] ?>/<?= $st['total'] ?> <?= $doneAll ? '🏆' : '' ?>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$problems): ?>
    <div class="card empty-state">
      <p>No problems match that filter. <a href="problems.php">Clear it</a>.</p>
    </div>
  <?php else: ?>
  <div class="problem-grid">
    <?php foreach ($problems as $p):
        $isSolved = isset($solved[(int)$p['id']]);
        $tags = array_filter(array_map('trim', explode(',', $p['tags'])));
    ?>
    <a class="card problem-card <?= $p['category'] === 'ai' ? 'ai-card' : '' ?>" href="problem.php?slug=<?= urlencode($p['slug']) ?>">
      <div class="problem-card-top">
        <span class="badge <?= difficulty_badge_class($p['difficulty']) ?>"><?= e($p['difficulty']) ?></span>
        <span class="tag <?= $p['category'] === 'ai' ? 'ai-tag' : 'cat-tag' ?>"><?= e($catLabels[$p['category']] ?? $p['category']) ?></span>
        <?php if ($isSolved): ?><span class="solved-check" title="Solved">✓ solved</span><?php endif; ?>
      </div>
      <h3><?= e($p['title']) ?></h3>
      <div class="tag-row">
        <?php foreach (array_slice($tags, 0, 3) as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?>
      </div>
      <div class="problem-card-meta">
        <span><?= (int)$p['points'] ?> pts</span>
        <span><?= (int)$p['attempts'] ?> attempts</span>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

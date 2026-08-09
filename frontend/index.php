<?php
require __DIR__ . '/../backend/lib/bootstrap.php';

$page_title = 'Codeface — Practice coding with real people';
$active = 'home';

$stats = [
    'problems'    => (int)(db_one('SELECT COUNT(*) AS c FROM problems WHERE ai_user_id IS NULL')['c'] ?? 0),
    'coders'      => (int)(db_one('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
    'submissions' => (int)(db_one('SELECT COUNT(*) AS c FROM submissions')['c'] ?? 0),
];
$liveRooms = db_all(
    'SELECT r.code, r.name, r.language,
            (SELECT COUNT(*) FROM room_members m WHERE m.room_id = r.id AND m.left_at IS NULL) AS members
     FROM rooms r WHERE r.is_live = 1 ORDER BY r.created_at DESC LIMIT 3'
);
$trending = db_all(
    'SELECT p.slug, p.title, p.difficulty, p.points, COUNT(s.id) AS attempts
     FROM problems p LEFT JOIN submissions s ON s.problem_id = p.id
     WHERE p.ai_user_id IS NULL
     GROUP BY p.id ORDER BY attempts DESC, p.id ASC LIMIT 3'
);

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <p class="eyebrow">Practice · Pair · Compete</p>
      <h1>A gym for <span class="accent">coders</span>.</h1>
      <p class="lede">
        You already know the basics. Codeface builds fluency: bite-size problems with instant
        feedback, live pair-programming rooms, and an AI that keeps generating fresh practice as you clear the board.
      </p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="problems.php">Start practicing</a>
        <a class="btn btn-outline btn-lg" href="rooms.php">Open a live room</a>
      </div>
      <div class="hero-stats">
        <div class="stat"><span class="stat-num"><?= $stats['problems'] ?></span><span class="stat-label">problems</span></div>
        <div class="stat"><span class="stat-num"><?= $stats['coders'] ?></span><span class="stat-label">coders</span></div>
        <div class="stat"><span class="stat-num"><?= $stats['submissions'] ?></span><span class="stat-label">submissions</span></div>
      </div>
    </div>
    <div class="hero-editor" aria-hidden="true">
      <div class="editor-chrome"><span></span><span></span><span></span><em>two-sum.js — room DEMO42</em></div>
<pre class="editor-mock"><code><i>// alice &amp; bob, pairing live</i>
<fn>function</fn> <kw>twoSum</kw>(nums, target) {
  <fn>const</fn> seen = <fn>new</fn> <kw>Map</kw>();
  <fn>for</fn> (<fn>let</fn> i = 0; i &lt; nums.length; i++) {
    <fn>const</fn> need = target - nums[i];
    <fn>if</fn> (seen.has(need)) <fn>return</fn> [seen.get(need), i];
    seen.set(nums[i], i);
  }
}</code></pre>
      <div class="editor-status"><span class="dot ok"></span> 4/4 tests passing · 2 online</div>
    </div>
  </div>
</section>

<section class="container pillars">
  <div class="pillar card">
    <div class="pillar-icon">⌨️</div>
    <h3>Practice</h3>
    <p><?= $stats['problems'] ?>+ problems in <?= count(cf_languages()) ?> languages with an in-browser editor and instant test feedback — no setup, no waiting.</p>
    <a href="problems.php" class="card-link">Browse problems →</a>
  </div>
  <div class="pillar card">
    <div class="pillar-icon">📚</div>
    <h3>Learn</h3>
    <p>Twelve hands-on tracks — JavaScript to Rust — that teach each language through real-world scenarios, not dry syntax charts.</p>
    <a href="learn.php" class="card-link">Start learning →</a>
  </div>
  <div class="pillar card">
    <div class="pillar-icon">🧑‍🤝‍🧑</div>
    <h3>Pair live</h3>
    <p>Spin up a shared room in seconds. Real-time pads, chat, and presence — mentoring without the screen-share gymnastics.</p>
    <a href="rooms.php" class="card-link">See live rooms →</a>
  </div>
  <div class="pillar card">
    <div class="pillar-icon">🏁</div>
    <h3>Compete</h3>
    <p>A points leaderboard and skill-based matchmaking for when you want stakes with your reps.</p>
    <a href="leaderboard.php" class="card-link">See the leaderboard →</a>
  </div>
  <div class="pillar card">
    <div class="pillar-icon">🐞</div>
    <h3>Pro Labs</h3>
    <p>Multi-file legacy codebases with real bugs to hunt, and vendor-style APIs to integrate — engineering, not just syntax.</p>
    <a href="labs.php" class="card-link">Open an environment →</a>
  </div>
  <div class="pillar card">
    <div class="pillar-icon">🧹</div>
    <h3>Refactor Gym</h3>
    <p>Real jobs are 80% maintenance. Clean up messy repos, keep the safety tests green, and let the senior-engineer reviewer grade your craft.</p>
    <a href="refactor.php" class="card-link">Fix a repo →</a>
  </div>
</section>

<section class="container two-col-home">
  <div class="card">
    <div class="card-head">
      <h3>Trending problems</h3>
      <a href="problems.php" class="muted-link">all problems →</a>
    </div>
    <ul class="mini-list">
      <?php foreach ($trending as $t): ?>
      <li>
        <a href="problem.php?slug=<?= urlencode($t['slug']) ?>"><?= e($t['title']) ?></a>
        <span class="badge <?= difficulty_badge_class($t['difficulty']) ?>"><?= e($t['difficulty']) ?></span>
        <span class="mini-meta"><?= (int)$t['attempts'] ?> attempts · <?= (int)$t['points'] ?> pts</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <div class="card-head">
      <h3>Live rooms</h3>
      <a href="rooms.php" class="muted-link">all rooms →</a>
    </div>
    <?php if (!$liveRooms): ?>
      <p class="empty-note">No live rooms yet — <a href="rooms.php">create the first one</a>.</p>
    <?php else: ?>
    <ul class="mini-list">
      <?php foreach ($liveRooms as $r): ?>
      <li>
        <a href="room.php?code=<?= urlencode($r['code']) ?>"><?= e($r['name']) ?></a>
        <code class="room-code"><?= e($r['code']) ?></code>
        <span class="mini-meta"><?= (int)$r['members'] ?> in room</span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

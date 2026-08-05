<?php
require __DIR__ . '/lib/bootstrap.php';
$me = current_user();

$username = (string)($_GET['u'] ?? ($me['username'] ?? ''));
$user = db_one(
    'SELECT id, username, display_name, bio, avatar_color, rating, created_at FROM users WHERE username = ?',
    [$username]
);
if (!$user) {
    http_response_code(404);
    $page_title = 'Profile not found';
    $active = '';
    require __DIR__ . '/partials/head.php';
    require __DIR__ . '/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>No coder by that name</h1><p><a href="leaderboard.php">See the leaderboard</a></p></div></div>';
    require __DIR__ . '/partials/footer.php';
    exit;
}
$uid = (int)$user['id'];

$stats = db_one(
    "SELECT COUNT(DISTINCT CASE WHEN status = 'pass' THEN problem_id END) AS solved,
            COUNT(*) AS subs
     FROM submissions WHERE user_id = ?",
    [$uid]
);
$points = db_one(
    "SELECT COALESCE(SUM(p.points),0) AS pts FROM (
        SELECT DISTINCT problem_id FROM submissions WHERE user_id = ? AND status = 'pass'
     ) x JOIN problems p ON p.id = x.problem_id",
    [$uid]
)['pts'] ?? 0;

$solved = db_all(
    "SELECT p.slug, p.title, p.difficulty, p.points, MIN(s.created_at) AS first_at
     FROM submissions s JOIN problems p ON p.id = s.problem_id
     WHERE s.user_id = ? AND s.status = 'pass'
     GROUP BY p.id ORDER BY first_at DESC",
    [$uid]
);
$recent = db_all(
    "SELECT s.status, s.passed, s.total, s.created_at, p.slug, p.title
     FROM submissions s JOIN problems p ON p.id = s.problem_id
     WHERE s.user_id = ? ORDER BY s.id DESC LIMIT 10",
    [$uid]
);
$hacks = db_all(
    'SELECT h.name, h.slug FROM hackathon_participants hp JOIN hackathons h ON h.id = hp.hackathon_id WHERE hp.user_id = ? ORDER BY h.starts_at DESC',
    [$uid]
);
$isMe = $me && (int)$me['id'] === $uid;

$page_title = $user['username'] . ' — profile';
$active = $isMe ? 'profile' : '';

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container">
  <div class="card profile-head">
    <span class="avatar xl" style="background:<?= e($user['avatar_color']) ?>"><?= e(strtoupper(substr($user['username'], 0, 1))) ?></span>
    <div class="profile-id">
      <h1><?= e($user['display_name'] ?: $user['username']) ?>
        <?php if ($isMe): ?><span class="you-pill">you</span><?php endif; ?>
      </h1>
      <p class="muted">@<?= e($user['username']) ?> · joined <time data-ts="<?= e($user['created_at']) ?>"><?= e($user['created_at']) ?></time></p>
      <?php if ($user['bio']): ?><p class="bio"><?= e($user['bio']) ?></p><?php endif; ?>
    </div>
    <div class="stat-cards">
      <div class="stat-card"><span class="stat-num"><?= (int)$user['rating'] ?></span><span class="stat-label">rating</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)$points ?></span><span class="stat-label">points</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)($stats['solved'] ?? 0) ?></span><span class="stat-label">solved</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)($stats['subs'] ?? 0) ?></span><span class="stat-label">submissions</span></div>
    </div>
  </div>

  <div class="profile-grid">
    <div class="card">
      <div class="card-head"><h3>Solved (<?= count($solved) ?>)</h3></div>
      <?php if (!$solved): ?>
        <p class="empty-note">Nothing solved yet<?= $isMe ? ' — <a href="problems.php">pick your first problem</a>' : '' ?>.</p>
      <?php else: ?>
      <div class="badge-cloud">
        <?php foreach ($solved as $s): ?>
          <a class="solve-chip <?= difficulty_badge_class($s['difficulty']) ?>" href="problem.php?slug=<?= urlencode($s['slug']) ?>" title="<?= (int)$s['points'] ?> pts">
            ✓ <?= e($s['title']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($hacks): ?>
      <div class="card-head" style="margin-top:1.2rem"><h3>Hackathons</h3></div>
      <div class="badge-cloud">
        <?php foreach ($hacks as $h): ?><a class="tag tag-link" href="hackathons.php"><?= e($h['name']) ?></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="card table-card">
      <div class="card-head"><h3>Recent submissions</h3></div>
      <?php if (!$recent): ?>
        <p class="empty-note">No submissions yet.</p>
      <?php else: ?>
      <table class="lb-table compact">
        <thead><tr><th>Problem</th><th>Result</th><th>When</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="problem.php?slug=<?= urlencode($r['slug']) ?>"><?= e($r['title']) ?></a></td>
            <td>
              <?php if ($r['status'] === 'pass'): ?>
                <span class="result-pill pass">pass <?= (int)$r['passed'] ?>/<?= (int)$r['total'] ?></span>
              <?php else: ?>
                <span class="result-pill fail">fail <?= (int)$r['passed'] ?>/<?= (int)$r['total'] ?></span>
              <?php endif; ?>
            </td>
            <td><time data-ts="<?= e($r['created_at']) ?>"><?= e($r['created_at']) ?></time></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$inline_script = "CF.timeagoAll();";
require __DIR__ . '/partials/footer.php';
?>

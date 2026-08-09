<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
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
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>No coder by that name</h1><p><a href="leaderboard.php">See the leaderboard</a></p></div></div>';
    require __DIR__ . '/../backend/partials/footer.php';
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
$isMe = $me && (int)$me['id'] === $uid;

/* Journey/section statuses — needs the avatar column too, so fetch the full row. */
$user = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
$journey = cf_section_journey($uid);
$flash = $_SESSION['flash_profile'] ?? null;
unset($_SESSION['flash_profile']);

$page_title = $user['username'] . ' — profile';
$active = $isMe ? 'profile' : '';

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container">
  <?php if ($flash): ?>
    <div class="alert <?= $flash['type'] === 'success' ? 'alert-success' : 'alert-error' ?>"><?= e($flash['text']) ?></div>
  <?php endif; ?>

  <div class="card profile-head">
    <div class="avatar-edit-wrap">
      <?= cf_avatar_html($user, 'avatar xl') ?>
      <?php if ($isMe): ?>
        <form id="avatarForm" method="post" action="../backend/api/profile/avatar.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <input id="avatarPick" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
          <label class="avatar-edit-badge" for="avatarPick" title="Change profile photo">✏️</label>
        </form>
      <?php endif; ?>
    </div>
    <div class="profile-id">
      <h1><?= e($user['display_name'] ?: $user['username']) ?>
        <?php if ($isMe): ?><span class="you-pill">you</span>
          <button class="edit-link" id="btnEditName" type="button" title="Edit name &amp; bio">✏️ edit name</button>
        <?php endif; ?>
      </h1>
      <p class="muted">@<?= e($user['username']) ?> · joined <time data-ts="<?= e($user['created_at']) ?>"><?= e($user['created_at']) ?></time></p>
      <?php if ($user['bio']): ?><p class="bio"><?= e($user['bio']) ?></p><?php endif; ?>

      <?php if ($isMe): ?>
      <form class="name-edit-form" id="nameEditForm" method="post" action="../backend/api/profile/update.php" hidden>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="field">
          <span>Display name</span>
          <input class="input" type="text" name="display_name" maxlength="40" value="<?= e($user['display_name']) ?>" placeholder="<?= e($user['username']) ?>">
        </label>
        <label class="field">
          <span>Bio <em class="muted">(optional)</em></span>
          <textarea class="input" name="bio" rows="2" maxlength="220" placeholder="One line about you…"><?= e($user['bio']) ?></textarea>
        </label>
        <div class="name-edit-actions">
          <button class="btn btn-primary btn-sm" type="submit">Save</button>
          <button class="btn btn-ghost btn-sm" id="btnEditCancel" type="button">Cancel</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
    <div class="stat-cards">
      <div class="stat-card"><span class="stat-num"><?= (int)$user['rating'] ?></span><span class="stat-label">rating</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)$points ?></span><span class="stat-label">points</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)($stats['solved'] ?? 0) ?></span><span class="stat-label">solved</span></div>
      <div class="stat-card"><span class="stat-num"><?= (int)($stats['subs'] ?? 0) ?></span><span class="stat-label">submissions</span></div>
    </div>
  </div>

  <?php
    $sections = [
      'practice' => ['⚔️ Practice', 'problems.php', $journey['practice']],
      'labs'     => ['🧪 Pro Labs', 'labs.php', $journey['labs']],
      'refactor' => ['🧹 Refactor Gym', 'refactor.php', $journey['refactor']],
    ];
    $allCompleteLearn = 0; foreach ($journey['learn'] as $t) if ($t['status'] === 'complete ✓') $allCompleteLearn++;
  ?>
  <div class="card journey-card">
    <div class="card-head"><h3>📍 <?= $isMe ? 'Your' : e($user['username']) . '’s' ?> journey — every section, with status</h3></div>

    <div class="journey-rows">
      <?php foreach ($sections as $key => [$label, $href, $s]): [$stLabel, $stCls] = cf_journey_status($s); ?>
      <?php $pct = $s['total'] > 0 ? min(100, (int)round(100 * $s['done'] / $s['total'])) : 0; ?>
      <a class="journey-row" href="<?= e($href) ?>">
        <span class="journey-name"><?= e($label) ?></span>
        <span class="journey-bar"><span class="progress"><span style="width:<?= $pct ?>%"></span></span>
          <em><?= (int)$s['done'] ?>/<?= (int)$s['total'] ?><?= !empty($s['extra']) ? ' ' . e($s['extra']) : '' ?></em>
        </span>
        <span class="st-chip <?= e($stCls) ?>"><?= e($stLabel) ?></span>
      </a>
      <?php endforeach; ?>

      <div class="journey-row journey-learn-head">
        <span class="journey-name">📚 Learn — languages with status</span>
        <span class="journey-bar">
          <?php $ld = 0; $lt = 0; foreach ($journey['learn'] as $t) { $ld += $t['done']; $lt += $t['total']; } ?>
          <span class="progress"><span style="width:<?= $lt ? (int)round(100 * $ld / $lt) : 0 ?>%"></span></span>
          <em><?= $ld ?>/<?= $lt ?> lessons · <?= $allCompleteLearn ?>/<?= count($journey['learn']) ?> languages complete</em>
        </span>
        <?php if ($ld === 0): ?><span class="st-chip st-todo">not started</span>
        <?php elseif ($allCompleteLearn === count($journey['learn'])): ?><span class="st-chip st-done">all done ✓</span>
        <?php else: ?><span class="st-chip st-ongoing">ongoing</span><?php endif; ?>
      </div>

      <div class="learn-status-list">
        <?php foreach ($journey['learn'] as $t):
            $cls = ['complete ✓' => 'st-done', 'ongoing' => 'st-ongoing', 'not started' => 'st-todo'][$t['status']] ?? 'st-todo';
            $pct = $t['total'] ? (int)round(100 * $t['done'] / $t['total']) : 0; ?>
        <a class="learn-mini" href="learn-track.php?l=<?= urlencode($t['id']) ?>" title="<?= e($t['name']) ?>">
          <span class="learn-mini-name"><?= e($t['name']) ?></span>
          <span class="learn-mini-bar"><span class="progress"><span style="width:<?= $pct ?>%"></span></span></span>
          <span class="learn-mini-count"><?= (int)$t['done'] ?>/<?= (int)$t['total'] ?></span>
          <span class="st-chip <?= e($cls) ?>"><?= e($t['status']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
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
$inline_script = "CF.timeagoAll();\n"
  . "var p=document.getElementById('avatarPick');if(p){p.addEventListener('change',function(){if(p.files&&p.files.length){document.getElementById('avatarForm').submit();}});}\n"
  . "var b=document.getElementById('btnEditName'),f=document.getElementById('nameEditForm'),c=document.getElementById('btnEditCancel');\n"
  . "if(b&&f){b.addEventListener('click',function(){f.hidden=!f.hidden;if(!f.hidden){var i=f.querySelector('input[name=display_name]');if(i)i.focus();}});if(c){c.addEventListener('click',function(){f.hidden=true;});}}";
require __DIR__ . '/../backend/partials/footer.php';
?>

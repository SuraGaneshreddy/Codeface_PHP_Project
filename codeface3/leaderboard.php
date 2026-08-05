<?php
require __DIR__ . '/lib/bootstrap.php';

$page_title = 'Leaderboard';
$active = 'leaderboard';

$rows = db_all(
    'SELECT u.id, u.username, u.display_name, u.avatar_color, u.rating,
            COALESCE(t.pts, 0)    AS pts,
            COALESCE(t.solved, 0) AS solved,
            t.first_solve, t.last_solve
     FROM users u
     LEFT JOIN (
        SELECT x.user_id,
               SUM(p.points)        AS pts,
               COUNT(*)             AS solved,
               MIN(x.first_at)      AS first_solve,
               MAX(x.last_at)       AS last_solve
        FROM (
            SELECT user_id, problem_id, MIN(created_at) AS first_at, MAX(created_at) AS last_at
            FROM submissions WHERE status = \'pass\'
            GROUP BY user_id, problem_id
        ) x
        JOIN problems p ON p.id = x.problem_id
        GROUP BY x.user_id
     ) t ON t.user_id = u.id
     ORDER BY pts DESC, u.rating DESC, last_solve ASC, u.username ASC'
);
$me = current_user();

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Leaderboard</h1>
      <p class="page-sub">Points come from first-time solves: easy 10 · medium 20 · hard 35. Ties break on who got there first.</p>
    </div>
  </div>

  <?php
  $top3 = array_slice($rows, 0, 3);
  $rest = array_slice($rows, 3);
  $podiumOrder = [1, 0, 2]; // classic 2nd–1st–3rd visual order
  ?>
  <div class="podium">
    <?php foreach ($podiumOrder as $idx): if (!isset($top3[$idx])) continue; $u = $top3[$idx]; $rank = $idx + 1; ?>
      <a class="podium-slot rank-<?= $rank ?>" href="profile.php?u=<?= urlencode($u['username']) ?>">
        <span class="avatar lg" style="background:<?= e($u['avatar_color']) ?>"><?= e(strtoupper(substr($u['username'], 0, 1))) ?></span>
        <span class="podium-name"><?= e($u['username']) ?></span>
        <span class="podium-pts"><?= (int)$u['pts'] ?> pts</span>
        <span class="podium-rank"><?= $rank ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card table-card">
    <table class="lb-table">
      <thead>
        <tr><th>#</th><th>Coder</th><th class="num">Rating</th><th class="num">Solved</th><th class="num">Points</th><th>Last solve</th></tr>
      </thead>
      <tbody>
        <?php $rank = 0; foreach ($rows as $u): $rank++; ?>
        <tr class="<?= $me && $me['id'] === (int)$u['id'] ? 'me-row' : '' ?>">
          <td class="rank"><?= $rank ?></td>
          <td>
            <a class="user-cell" href="profile.php?u=<?= urlencode($u['username']) ?>">
              <span class="avatar" style="background:<?= e($u['avatar_color']) ?>"><?= e(strtoupper(substr($u['username'], 0, 1))) ?></span>
              <?= e($u['username']) ?>
              <?= $me && $me['id'] === (int)$u['id'] ? '<span class="you-pill">you</span>' : '' ?>
            </a>
          </td>
          <td class="num"><?= (int)$u['rating'] ?></td>
          <td class="num"><?= (int)$u['solved'] ?></td>
          <td class="num pts"><?= (int)$u['pts'] ?></td>
          <td><?= $u['last_solve'] ? '<time data-ts="' . e($u['last_solve']) . '">' . e($u['last_solve']) . '</time>' : '<span class="muted">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$inline_script = "CF.timeagoAll();";
require __DIR__ . '/partials/footer.php';
?>

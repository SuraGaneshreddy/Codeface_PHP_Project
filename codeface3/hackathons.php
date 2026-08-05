<?php
require __DIR__ . '/lib/bootstrap.php';
$me = current_user();

$page_title = 'Hackathons';
$active = 'hackathons';

$hacks = db_all('SELECT * FROM hackathons ORDER BY starts_at DESC');
$now = time();

foreach ($hacks as &$h) {
    $start = strtotime($h['starts_at'] . ' UTC');
    $end   = strtotime($h['ends_at'] . ' UTC');
    $h['_start'] = $start; $h['_end'] = $end;
    $h['_status'] = $now < $start ? 'upcoming' : ($now > $end ? 'ended' : 'live');
    $h['_problems'] = db_all(
        'SELECT p.slug, p.title, p.difficulty FROM hackathon_problems hp JOIN problems p ON p.id = hp.problem_id WHERE hp.hackathon_id = ? ORDER BY p.points ASC',
        [$h['id']]
    );
    $h['_participants'] = db_all(
        'SELECT u.username, u.avatar_color FROM hackathon_participants hp JOIN users u ON u.id = hp.user_id WHERE hp.hackathon_id = ? LIMIT 12',
        [$h['id']]
    );
    $h['_joined'] = $me ? (bool)db_one('SELECT user_id FROM hackathon_participants WHERE hackathon_id = ? AND user_id = ?', [$h['id'], $me['id']]) : false;
}
unset($h);

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Hackathons</h1>
      <p class="page-sub">Community events with a shared problem set. Join, solve on your own schedule, and compare notes in a room.</p>
    </div>
  </div>

  <div class="hack-grid">
    <?php foreach ($hacks as $h): ?>
    <div class="card hack-card" data-id="<?= (int)$h['id'] ?>">
      <div class="hack-head">
        <h3><?= e($h['name']) ?></h3>
        <span class="hack-status status-<?= $h['_status'] ?>">
          <?php if ($h['_status'] === 'live'): ?><span class="dot ok"></span> Live now
          <?php elseif ($h['_status'] === 'upcoming'): ?>Upcoming
          <?php else: ?>Ended<?php endif; ?>
        </span>
      </div>
      <p class="muted"><?= e($h['description']) ?></p>
      <div class="hack-dates">
        <?= e(gmdate('M j', $h['_start'])) ?> → <?= e(gmdate('M j, Y', $h['_end'])) ?> UTC
        <?php if ($h['_status'] === 'live'): ?>
          · <span class="countdown" data-countdown="<?= e(gmdate('c', $h['_end'])) ?>"></span> left
        <?php elseif ($h['_status'] === 'upcoming'): ?>
          · starts in <span class="countdown" data-countdown="<?= e(gmdate('c', $h['_start'])) ?>"></span>
        <?php endif; ?>
      </div>
      <div class="hack-problems">
        <?php foreach ($h['_problems'] as $p): ?>
          <a class="tag tag-link" href="problem.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="hack-foot">
        <div class="participant-row">
          <?php foreach ($h['_participants'] as $pt): ?>
            <span class="avatar sm" style="background:<?= e($pt['avatar_color']) ?>" title="<?= e($pt['username']) ?>"><?= e(strtoupper(substr($pt['username'], 0, 1))) ?></span>
          <?php endforeach; ?>
          <span class="muted participant-count"><?= count($h['_participants']) ?> joined</span>
        </div>
        <?php if ($me): ?>
        <button class="btn btn-sm <?= $h['_joined'] ? 'btn-ghost' : 'btn-primary' ?> hack-join-btn"
                data-id="<?= (int)$h['id'] ?>" data-joined="<?= $h['_joined'] ? '1' : '0' ?>">
          <?= $h['_joined'] ? 'Leave' : 'Join' ?>
        </button>
        <?php else: ?>
        <a class="btn btn-sm btn-primary" href="login.php?next=hackathons.php">Log in to join</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
$page_scripts = ['assets/js/hackathons.js'];
require __DIR__ . '/partials/footer.php';
?>

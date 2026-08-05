<?php
require __DIR__ . '/lib/bootstrap.php';
$me = require_login();

$page_title = 'Live rooms';
$active = 'rooms';

$rooms = db_all(
    "SELECT r.code, r.name, r.language, r.created_at, u.username AS owner,
            p.title AS problem_title,
            (SELECT COUNT(*) FROM room_members m WHERE m.room_id = r.id AND m.left_at IS NULL) AS members,
            (SELECT COUNT(*) FROM room_members m WHERE m.room_id = r.id AND m.left_at IS NULL
              AND m.last_seen IS NOT NULL AND m.last_seen >= ?) AS online
     FROM rooms r
     LEFT JOIN users u ON u.id = r.owner_id
     LEFT JOIN problems p ON p.id = r.problem_id
     WHERE r.is_live = 1
     ORDER BY online DESC, r.created_at DESC
     LIMIT 30",
    [ts(time() - 15)]
);

$problems = db_all('SELECT id, title, difficulty FROM problems ORDER BY id ASC');

require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container">
  <div class="page-head">
    <div>
      <h1>Live rooms</h1>
      <p class="page-sub">Pair-program in real time: shared pads, chat, and presence. Share the room code to invite anyone.</p>
    </div>
  </div>

  <div class="lobby-grid">
    <div class="card">
      <h3>Create a room</h3>
      <form id="createRoomForm" class="stacked-form">
        <label class="field">
          <span>Room name</span>
          <input class="input" type="text" name="name" maxlength="60" placeholder="e.g. Two Sum with Sam" required>
        </label>
        <div class="field-row">
          <label class="field">
            <span>Pad language</span>
            <select class="input" name="language">
              <?php foreach (cf_language_names() as $lid => $lname): ?>
              <option value="<?= e($lid) ?>"<?= $lid === 'javascript' ? ' selected' : '' ?>><?= e($lname) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">
            <span>Attach a problem (optional)</span>
            <select class="input" name="problem_id">
              <option value="">— free coding —</option>
              <?php foreach ($problems as $p): ?>
              <option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?> (<?= e($p['difficulty']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <button class="btn btn-primary" type="submit">Create room</button>
      </form>
      <hr class="sep">
      <form class="inline-form" method="get" action="room.php" onsubmit="return this.code.value = this.code.value.toUpperCase().trim();">
        <input class="input" type="text" name="code" maxlength="12" placeholder="Have a code? e.g. DEMO42" required>
        <button class="btn btn-outline" type="submit">Join</button>
      </form>
    </div>

    <div class="card">
      <h3>Skill matchmaking</h3>
      <p class="muted">Get paired with someone at your level into a private room. Closest rating wins the match.</p>
      <form id="matchForm" class="stacked-form">
        <div class="field-row">
          <label class="field">
            <span>Language</span>
            <select class="input" name="language">
              <?php foreach (cf_language_names() as $lid => $lname): ?>
              <option value="<?= e($lid) ?>"<?= $lid === 'javascript' ? ' selected' : '' ?>><?= e($lname) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field">
            <span>Difficulty</span>
            <select class="input" name="difficulty">
              <option value="easy">Easy</option>
              <option value="medium" selected>Medium</option>
              <option value="hard">Hard</option>
            </select>
          </label>
        </div>
        <button class="btn btn-primary" type="submit" id="mmBtn">Find a match</button>
        <div class="mm-status" id="mmStatus"></div>
      </form>
    </div>
  </div>

  <h2 class="section-title">Open rooms</h2>
  <?php if (!$rooms): ?>
    <div class="card empty-state"><p>No live rooms right now. Be the first to create one!</p></div>
  <?php else: ?>
  <div class="rooms-grid">
    <?php foreach ($rooms as $r): ?>
    <div class="card room-card">
      <div class="room-card-head">
        <h3><a href="room.php?code=<?= urlencode($r['code']) ?>"><?= e($r['name']) ?></a></h3>
        <?php if ((int)$r['online'] > 0): ?><span class="live-pill"><span class="dot ok"></span><?= (int)$r['online'] ?> online</span><?php endif; ?>
      </div>
      <div class="room-card-meta">
        <code class="room-code"><?= e($r['code']) ?></code>
        <span class="tag"><?= e(cf_language_names()[$r['language']] ?? $r['language']) ?></span>
        <?php if ($r['problem_title']): ?><span class="tag">📎 <?= e($r['problem_title']) ?></span><?php endif; ?>
      </div>
      <div class="room-card-foot">
        <span class="muted">host <?= e($r['owner'] ?? '?') ?> · <?= (int)$r['members'] ?> member<?= (int)$r['members'] === 1 ? '' : 's' ?></span>
        <a class="btn btn-sm btn-outline" href="room.php?code=<?= urlencode($r['code']) ?>">Join room</a>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$page_scripts = ['assets/js/rooms.js'];
require __DIR__ . '/partials/footer.php';
?>

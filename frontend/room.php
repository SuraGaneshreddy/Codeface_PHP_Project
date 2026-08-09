<?php
require __DIR__ . '/../backend/lib/bootstrap.php';
$me = require_login();

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$room = $code !== '' ? room_by_code($code) : null;
if (!$room) {
    http_response_code(404);
    $page_title = 'Room not found';
    $active = 'rooms';
    require __DIR__ . '/../backend/partials/head.php';
    require __DIR__ . '/../backend/partials/header.php';
    echo '<div class="container"><div class="card empty-state"><h1>Room not found</h1><p>Check the code and try again — codes look like <code>DEMO42</code>.</p><p><a href="rooms.php">Back to rooms</a></p></div></div>';
    require __DIR__ . '/../backend/partials/footer.php';
    exit;
}

room_join((int)$room['id'], (int)$me['id'], $room['owner_username'] === $me['username'] ? 'owner' : 'participant');

$payload = room_state_payload($room, (int)$me['id']);
$payload['me'] = ['id' => (int)$me['id'], 'username' => $me['username'], 'color' => $me['avatar_color']];
$languages = [];
foreach (cf_languages() as $id => $m) {
    $languages[] = ['id' => $id, 'name' => $m['name'], 'monaco' => $m['monaco'], 'runner' => $m['runner']];
}
$payload['languages'] = $languages;
if ($room['problem_id']) {
    $prob = db_one('SELECT slug, title, difficulty, description, function_name, tests_json FROM problems WHERE id = ?', [$room['problem_id']]);
    if ($prob) {
        $payload['problem'] = [
            'slug'         => $prob['slug'],
            'title'        => $prob['title'],
            'difficulty'   => $prob['difficulty'],
            'description'  => allow_html($prob['description']),
            'functionName' => $prob['function_name'],
            'fnNames'      => cf_fn_names_all($prob['function_name']),
            'tests'        => json_decode($prob['tests_json'], true) ?: [],
        ];
    }
}

$page_title = $room['name'] . ' (room ' . $room['code'] . ')';
$active = 'rooms';

require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<script id="roomData" type="application/json"><?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<div class="container wide room-wrap">
  <div class="room-topbar">
    <div class="room-id">
      <h1><?= e($room['name']) ?></h1>
      <button class="copy-btn" id="copyCode" title="Copy room code">code: <strong><?= e($room['code']) ?></strong> ⧉</button>
    </div>
    <div class="room-topbar-right">
      <span class="conn-pill" id="connPill"><span class="dot" id="connDot"></span><span id="connText">connecting…</span></span>
      <a class="btn btn-ghost btn-sm" href="rooms.php" id="leaveLink">Leave</a>
    </div>
  </div>

  <div class="room-grid">
    <aside class="room-side card">
      <div class="side-tabs">
        <button class="tab-btn active" data-tab="tabProblem" type="button">Problem</button>
        <button class="tab-btn" data-tab="tabPeople" type="button">People</button>
      </div>
      <div class="tab-panel" id="tabProblem">
        <?php if (!empty($payload['problem'])): ?>
          <h3><?= e($payload['problem']['title']) ?>
            <span class="badge <?= difficulty_badge_class($payload['problem']['difficulty']) ?>"><?= e($payload['problem']['difficulty']) ?></span>
          </h3>
          <div class="desc-body compact" id="roomProblemDesc"><?= $payload['problem']['description'] /* already whitelisted */ ?></div>
        <?php else: ?>
          <p class="muted">No problem attached — this is a free-coding pad. Describe your goal in the chat!</p>
        <?php endif; ?>
      </div>
      <div class="tab-panel hidden" id="tabPeople">
        <div id="presenceList" class="presence-list"></div>
      </div>
    </aside>

    <section class="room-main card">
      <div class="editor-toolbar">
        <div class="lang-tabs" id="langTabs">
          <?php foreach (cf_languages() as $key => $m): ?>
          <button class="lang-tab <?= $key === $room['language'] ? 'on' : '' ?>" data-lang="<?= e($key) ?>" type="button"><?= e($m['name']) ?></button>
          <?php endforeach; ?>
        </div>
        <span class="spacer"></span>
        <?php if (!empty($payload['problem'])): ?>
        <button class="btn btn-outline btn-sm" id="btnRun" type="button" title="Runs the attached problem's tests (JS / TS / Python pads)">▶ Run tests</button>
        <?php endif; ?>
      </div>
      <div id="editorHost" class="editor-host room-editor"></div>
      <div class="results-bar" id="resultsBar">
        <span id="editNote" class="muted">Edits sync to everyone in the room.</span>
        <span id="resultsSummary" class="muted"></span>
        <span id="resultsTime" class="muted"></span>
      </div>
      <div id="results" class="results"></div>
    </section>

    <aside class="room-chat card">
      <div class="chat-head">Chat <span class="chat-count" id="chatCount"></span></div>
      <div class="chat-list" id="chatList"></div>
      <form class="chat-input-row" id="chatForm">
        <input class="input" id="chatInput" maxlength="500" placeholder="Type and press Enter…" autocomplete="off">
        <button class="btn btn-primary btn-sm" type="submit">Send</button>
      </form>
    </aside>
  </div>
</div>

<?php
$page_scripts = ['assets/js/editor.js', 'assets/js/runner.js', 'assets/js/room.js'];
require __DIR__ . '/../backend/partials/footer.php';
?>

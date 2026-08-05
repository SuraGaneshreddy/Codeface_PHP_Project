<?php
declare(strict_types=1);

/**
 * Fresh-database seed: content (problems + lessons via content_seed), then
 * demo users, solve history, hackathons and the DEMO42 room.
 * Demo accounts (see README): alice / bob / carol / dev_mike — password: password123
 */
function seed_database(PDO $pdo): void {
    require_once __DIR__ . '/content_seed.php';
    $pdo->beginTransaction();
    try {
        cf_seed_content($pdo);
        $uids = seed_users($pdo);
        seed_submissions($pdo, $uids);
        seed_hackathons($pdo, $uids);
        seed_demo_room($pdo, $uids);
        cf_meta_set($pdo, 'schema_version', (string)CF_SCHEMA_VERSION);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function seed_users(PDO $pdo): array {
    $ins = $pdo->prepare(
        'INSERT INTO users (username, email, password_hash, display_name, bio, avatar_color, rating, created_at, last_seen)
         VALUES (?, ?, ?, ?, ?, ?, 1200, ?, ?)'
    );
    $hash = password_hash('password123', PASSWORD_DEFAULT);
    $specs = [
        ['alice',   'alice@codeface.dev',   'Alice K.',  'Full-stack curious. Pair-programming enthusiast.', '#6366f1'],
        ['bob',     'bob@codeface.dev',     'Bob R.',    'Grinding one medium a day.',                       '#22c55e'],
        ['carol',   'carol@codeface.dev',   'Carol D.',  'CS junior — here for the hackathons.',             '#f59e0b'],
        ['dev_mike','mike@codeface.dev',    'Mike T.',   'Career switcher. JavaScript first, fear later.',   '#06b6d4'],
    ];
    $uids = [];
    foreach ($specs as $i => [$u, $em, $dn, $bio, $col]) {
        $ins->execute([$u, $em, $hash, $dn, $bio, $col, ts(time() - (90 - $i * 7) * 86400), ts(time() - 3600 * ($i + 1))]);
        $uids[$u] = (int)$pdo->lastInsertId();
    }
    return $uids;
}

function seed_submissions(PDO $pdo, array $uids): void {
    $solves = [
        'alice'    => ['two-sum', 'fizzbuzz', 'reverse-string', 'palindrome-number', 'climbing-stairs', 'move-zeroes', 'max-subarray', 'group-anagrams', 'container-with-most-water'],
        'bob'      => ['fizzbuzz', 'reverse-string', 'two-sum', 'palindrome-number', 'climbing-stairs', 'move-zeroes', 'max-subarray'],
        'carol'    => ['fizzbuzz', 'reverse-string', 'palindrome-number', 'two-sum', 'climbing-stairs'],
        'dev_mike' => ['fizzbuzz', 'reverse-string', 'move-zeroes'],
    ];
    $prob = [];
    foreach (db_all('SELECT id, slug, points, tests_json FROM problems') as $p) $prob[$p['slug']] = $p;

    $ins = $pdo->prepare(
        "INSERT INTO submissions (user_id, problem_id, status, code, passed, total, runtime_ms, created_at)
         VALUES (?, ?, 'pass', ?, ?, ?, ?, ?)"
    );
    $ratings = [];
    $day = 40;
    foreach ($solves as $uname => $slugs) {
        $uid = $uids[$uname];
        $ratings[$uid] = 1200;
        foreach ($slugs as $slug) {
            $p = $prob[$slug];
            $total = count(json_decode($p['tests_json'], true));
            $ins->execute([$uid, $p['id'], "// {$uname}'s accepted solution for {$slug}", $total, $total, round(mt_rand(20, 900) / 10, 1), ts(time() - $day * 86400 + mt_rand(0, 40000))]);
            $ratings[$uid] += (int)$p['points'];
            $day -= 3;
        }
    }
    $up = $pdo->prepare('UPDATE users SET rating = ? WHERE id = ?');
    foreach ($ratings as $uid => $r) $up->execute([$r, $uid]);
}

function seed_hackathons(PDO $pdo, array $uids): void {
    $ins = $pdo->prepare('INSERT INTO hackathons (name, slug, description, starts_at, ends_at) VALUES (?, ?, ?, ?, ?)');
    $now = time();
    $ins->execute([
        'Arrays August Sprint', 'arrays-august-sprint',
        'A week-long sprint focused on array fundamentals — from two pointers to Kadane. Solve at your own pace, climb the board, and pair up in a room if you get stuck.',
        ts($now - 3 * 86400), ts($now + 4 * 86400),
    ]);
    $h1 = (int)$pdo->lastInsertId();
    $ins->execute([
        'String Theory Jam', 'string-theory-jam',
        'A weekend jam all about strings: anagrams, parentheses, and pattern thinking. Beginner friendly — mentors will be hanging out in the lobby room.',
        ts($now + 7 * 86400), ts($now + 9 * 86400),
    ]);
    $h2 = (int)$pdo->lastInsertId();
    $ins->execute([
        'Summer Showdown', 'summer-showdown',
        'Our very first community event. Warm-ups, stairs, and a string flip for good measure. Finished — see the results on the leaderboard.',
        ts($now - 30 * 86400), ts($now - 23 * 86400),
    ]);
    $h3 = (int)$pdo->lastInsertId();

    $link = $pdo->prepare('INSERT INTO hackathon_problems (hackathon_id, problem_id) SELECT ?, id FROM problems WHERE slug = ?');
    foreach (['two-sum', 'max-subarray', 'move-zeroes', 'container-with-most-water', 'trapping-rain-water', 'product-except-self', 'three-sum'] as $s) $link->execute([$h1, $s]);
    foreach (['reverse-string', 'palindrome-number', 'group-anagrams', 'longest-valid-parentheses', 'decode-string'] as $s) $link->execute([$h2, $s]);
    foreach (['fizzbuzz', 'climbing-stairs', 'reverse-string'] as $s) $link->execute([$h3, $s]);

    $join = $pdo->prepare('INSERT INTO hackathon_participants (hackathon_id, user_id) VALUES (?, ?)');
    $join->execute([$h1, $uids['alice']]);
    $join->execute([$h1, $uids['bob']]);
    $join->execute([$h2, $uids['carol']]);
    $join->execute([$h3, $uids['alice']]);
    $join->execute([$h3, $uids['bob']]);
    $join->execute([$h3, $uids['carol']]);
    $join->execute([$h3, $uids['dev_mike']]);
}

function seed_demo_room(PDO $pdo, array $uids): void {
    $problem = db_one('SELECT * FROM problems WHERE slug = ?', ['two-sum']);
    $problemId = (int)($problem['id'] ?? 0);
    $ins = $pdo->prepare('INSERT INTO rooms (code, name, owner_id, problem_id, language, is_live) VALUES (?, ?, ?, ?, ?, 1)');
    $ins->execute(['DEMO42', 'Two Sum — pair session', $uids['alice'], $problemId, 'javascript']);
    $roomId = (int)$pdo->lastInsertId();

    $pad = $pdo->prepare('INSERT INTO room_pads (room_id, language, content, version, last_editor_id) VALUES (?, ?, ?, ?, ?)');
    foreach (room_default_pads($problem) as $lang => $content) {
        $pad->execute([$roomId, $lang, $content, 0, null]);
    }
    $jsContent = <<<'JS'
/**
 * Two Sum — pair session
 * alice: let's do the hashmap pass
 * bob:   I'll type, you navigate
 */
function twoSum(nums, target) {
  const seen = new Map(); // value -> index
  for (let i = 0; i < nums.length; i++) {
    const need = target - nums[i];
    if (seen.has(need)) return [seen.get(need), i];
    seen.set(nums[i], i);
  }
  return [];
}
JS;
    $up = $pdo->prepare('UPDATE room_pads SET content = ?, version = 4, last_editor_id = ? WHERE room_id = ? AND language = ?');
    $up->execute([$jsContent, $uids['bob'], $roomId, 'javascript']);

    $mem = $pdo->prepare('INSERT INTO room_members (room_id, user_id, role, joined_at, last_seen) VALUES (?, ?, ?, ?, ?)');
    $mem->execute([$roomId, $uids['alice'], 'owner', ts(time() - 7200), ts(time() - 7200)]);
    $mem->execute([$roomId, $uids['bob'], 'participant', ts(time() - 7000), ts(time() - 7000)]);

    $chat = $pdo->prepare('INSERT INTO chat_messages (room_id, user_id, body, created_at) VALUES (?, ?, ?, ?)');
    $chat->execute([$roomId, $uids['alice'], 'want to try the hashmap approach?', ts(time() - 7000)]);
    $chat->execute([$roomId, $uids['bob'], 'yes — I type, you navigate', ts(time() - 6900)]);
    $chat->execute([$roomId, $uids['alice'], 'classic driver-navigator. go!', ts(time() - 6850)]);
}

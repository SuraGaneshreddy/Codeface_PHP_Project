<?php
declare(strict_types=1);

/**
 * Fresh-database seed: content (problems + lessons via content_seed), then
 * demo users, solve history and the DEMO42 room.
 * Demo accounts (see README): alice / bob / carol / dev_mike — password: password123
 */
function seed_database(PDO $pdo): void {
    require_once __DIR__ . '/content_seed.php';
    $pdo->beginTransaction();
    try {
        cf_seed_content($pdo);
        $uids = seed_users($pdo);
        seed_submissions($pdo, $uids);
        seed_learn_progress($pdo, $uids);
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
        ['carol',   'carol@codeface.dev',   'Carol D.',  'CS junior — labs-and-refactor enthusiast.',             '#f59e0b'],
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
        // 12 solves → Pro Labs / Refactor Gym unlocked for alice
        'alice'    => ['two-sum', 'fizzbuzz', 'reverse-string', 'palindrome-number', 'climbing-stairs', 'move-zeroes', 'max-subarray', 'group-anagrams', 'container-with-most-water', 'valid-parentheses', 'common-prefix', 'binary-search'],
        // 7/10 — three practice problems away from unlocking the gated sections
        'bob'      => ['fizzbuzz', 'reverse-string', 'two-sum', 'palindrome-number', 'climbing-stairs', 'move-zeroes', 'max-subarray'],
        // 10 solves → unlocked (matches her labs-and-refactor bio)
        'carol'    => ['fizzbuzz', 'reverse-string', 'palindrome-number', 'two-sum', 'climbing-stairs', 'valid-parentheses', 'binary-search', 'single-number', 'contains-duplicate', 'merge-alternately'],
        // 3/10 — perfect account for demoing the lock wall
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

/** Demo Learn progress so profile "language status" rows have something to show. */
function seed_learn_progress(PDO $pdo, array $uids): void {
    $plan = [
        'alice'    => ['javascript' => 6, 'python' => 3],          // two tracks ongoing
        'bob'      => ['javascript' => 2],
        'carol'    => ['javascript' => 16, 'sql' => 4],            // one full track ✓, one ongoing (💪)
        'dev_mike' => ['htmlcss' => 2],
    ];
    $ins = $pdo->prepare('INSERT INTO learn_progress (user_id, lesson_id, completed_at) VALUES (?, ?, ?)');
    $day = 30;
    foreach ($plan as $uname => $tracks) {
        foreach ($tracks as $track => $n) {
            foreach (db_all('SELECT id FROM learn_lessons WHERE track = ? ORDER BY position LIMIT ' . (int)$n, [$track]) as $row) {
                $ins->execute([$uids[$uname], (int)$row['id'], ts(time() - $day * 86400 + mt_rand(0, 30000))]);
                $day -= 1;
            }
        }
    }
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

<?php
declare(strict_types=1);

/* PHP 7.4 polyfills (XAMPP ships PHP 8.x, but just in case). */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool { return $needle === '' || strpos($haystack, $needle) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool { return strncmp($haystack, $needle, strlen($needle)) === 0; }
}

/** Escape for HTML output. */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Whitelisted HTML for problem descriptions (authored seed content, but stay safe). */
function allow_html(?string $html): string {
    return strip_tags((string)$html, '<p><pre><code><ul><ol><li><strong><em><b><i><h3><h4><br><hr><table><thead><tbody><tr><th><td>');
}

/** Current UTC timestamp in DB format. */
function now(): string { return gmdate('Y-m-d H:i:s'); }

function ts(int $unix): string { return gmdate('Y-m-d H:i:s', $unix); }

/** JSON helpers — always exit. */
function json_response($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $code = 400, array $extra = []): void {
    json_response(['ok' => false, 'error' => $message] + $extra, $code);
}

function require_method(string $method): void {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== strtoupper($method)) {
        json_error('Method not allowed', 405);
    }
}

/** Decode a JSON request body ({} if empty/invalid). */
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Session CSRF token (created lazily). */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Verify CSRF for state-changing requests.
 * Accepts the X-CSRF-Token header (fetch) or a `csrf` form field (plain forms / sendBeacon).
 */
function verify_csrf(bool $json = false): void {
    $token = csrf_token();
    $sent  = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($token, $sent)) {
        if ($json) json_error('Invalid CSRF token — refresh the page and try again.', 403);
        http_response_code(403);
        exit('Invalid CSRF token. Please go back, refresh, and try again.');
    }
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

/** Human-friendly room join codes (no ambiguous characters). */
function room_code(int $len = 6): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $out;
}

/** Gravatar-free avatar: initials circle rendered in CSS, we just store a hue. */
function random_avatar_color(): string {
    $palette = ['#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#06b6d4', '#a855f7', '#ec4899', '#84cc16'];
    return $palette[array_rand($palette)];
}

function difficulty_badge_class(string $d): string {
    return ['easy' => 'badge-easy', 'medium' => 'badge-medium', 'hard' => 'badge-hard'][$d] ?? 'badge-easy';
}

/** Points for newly solving a problem are stored on the problem row; rating bonus = points. */
function award_first_solve(int $userId, int $problemId): bool {
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) AS c FROM submissions WHERE user_id = ? AND problem_id = ? AND status = 'pass'");
    $st->execute([$userId, $problemId]);
    $already = (int)($st->fetch()['c'] ?? 0) > 0;
    if ($already) return false;
    $pts = (int)$pdo->query('SELECT points FROM problems WHERE id = ' . (int)$problemId)->fetch()['points'] ?? 0;
    $up = $pdo->prepare('UPDATE users SET rating = rating + ? WHERE id = ?');
    $up->execute([$pts, $userId]);
    return true;
}

/* ---------- Practice gate: Labs & Refactor Gym open after 10 solved problems ---------- */

/** The threshold. One source of truth (pages, partial and APIs all read it). */
function cf_practice_gate(): int { return 10; }

/** Distinct problem rows this user has passed (canonical + their own AI problems). */
function cf_solved_problems_count(?int $uid): int {
    if (!$uid) return 0;
    return (int)(db_one(
        "SELECT COUNT(DISTINCT problem_id) AS c FROM submissions WHERE user_id = ? AND status = 'pass'",
        [$uid]
    )['c'] ?? 0);
}

/* ---------- Profile avatars (upload) ---------- */

/** Absolute path of a user's uploaded avatar file, or null. */
function cf_avatar_file(?string $avatar): ?string {
    if (!$avatar) return null;
    $f = __DIR__ . '/../../database/data/avatars/' . basename($avatar);
    return is_file($f) ? $f : null;
}

/** Public URL for a user's uploaded avatar (passthrough; data/ is .htaccess-denied). */
function cf_avatar_url(array $user): ?string {
    $f = cf_avatar_file($user['avatar'] ?? null);
    if (!$f) return null;
    return 'avatar.php?id=' . (int)$user['id'] . '&v=' . filemtime($f);
}

/** Render the avatar: uploaded photo when present, else the initials circle. */
function cf_avatar_html(array $user, string $cls = 'avatar'): string {
    $url = cf_avatar_url($user);
    if ($url) {
        return '<img class="' . e($cls) . ' avatar-img" src="' . e($url) . '" alt="' . e($user['username']) . '">';
    }
    return '<span class="' . e($cls) . '" style="background:' . e($user['avatar_color'] ?? '#6366f1') . '">'
         . e(strtoupper(substr((string)$user['username'], 0, 1))) . '</span>';
}

/* ---------- Profile "sections journey" aggregation ---------- */
/** Status chip spec: [label, css] per section. */
function cf_journey_status(array $s): array {
    if (!empty($s['locked'])) return ['🔒 locked', 'st-locked'];
    if (($s['done'] ?? 0) <= 0)   return ['not started', 'st-todo'];
    if (!empty($s['open_ended'])) return ['🤖 AI sets running', 'st-ai'];
    if (($s['done'] ?? 0) >= ($s['total'] ?? 1)) return ['complete ✓', 'st-done'];
    return ['ongoing', 'st-ongoing'];
}

/**
 * Per-section completion picture for one user:
 *   practice / labs / refactor → [done, total, locked?, open_ended?, extra]
 *   learn → list of ['id','name','done','total','status'] sorted complete → ongoing → not started.
 */
function cf_section_journey(int $uid): array {
    $j = [];

    // Practice (canonical problems + this user's AI rows)
    $canon = (int)(db_one('SELECT COUNT(*) AS c FROM problems WHERE ai_user_id IS NULL')['c'] ?? 0);
    $solved = cf_solved_problems_count($uid);
    $ai = (int)(db_one('SELECT COUNT(*) AS c FROM problems WHERE ai_user_id = ?', [$uid])['c'] ?? 0);
    $j['practice'] = [
        'done' => $solved, 'total' => $canon, 'extra' => $ai ? "+{$ai} AI-made" : '',
        'open_ended' => $solved >= $canon && $canon > 0,
    ];

    // Pro Labs (canonical; AI sets counted as extra) — practice gate applies
    $labTotal = count(cf_labs());
    $labDone = (int)(db_one('SELECT COUNT(*) AS c FROM lab_progress WHERE user_id = ?', [$uid])['c'] ?? 0);
    [$labBatch] = cf_ai_labs_unlock(db(), $uid);
    $j['labs'] = [
        'done' => min($labDone, $labTotal), 'total' => $labTotal,
        'locked' => $solved < cf_practice_gate(),
        'extra' => $labDone > $labTotal ? '+' . ($labDone - $labTotal) . ' AI' : ($labBatch ? "{$labBatch} AI set" . ($labBatch > 1 ? 's' : '') : ''),
        'open_ended' => false,
    ];

    // Refactor Gym
    $rfTotal = count(cf_refactors());
    $rfDone = (int)(db_one(
        'SELECT COUNT(DISTINCT challenge_slug) AS c FROM refactor_runs WHERE user_id = ? AND tests_passed = tests_total AND tests_total > 0',
        [$uid]
    )['c'] ?? 0);
    [$rfBatch] = cf_ai_refactor_unlock(db(), $uid);
    $j['refactor'] = [
        'done' => min($rfDone, $rfTotal), 'total' => $rfTotal,
        'locked' => $solved < cf_practice_gate(),
        'extra' => $rfDone > $rfTotal ? '+' . ($rfDone - $rfTotal) . ' AI' : ($rfBatch ? "{$rfBatch} AI set" . ($rfBatch > 1 ? 's' : '') : ''),
        'open_ended' => false,
    ];

    // Learn — per-language status
    $counts = [];
    foreach (db_all('SELECT track, COUNT(*) AS c FROM learn_lessons GROUP BY track') as $r) $counts[$r['track']] = (int)$r['c'];
    $done = [];
    foreach (db_all(
        'SELECT l.track, COUNT(*) AS c FROM learn_progress p JOIN learn_lessons l ON l.id = p.lesson_id WHERE p.user_id = ? GROUP BY l.track',
        [$uid]
    ) as $r) $done[$r['track']] = (int)$r['c'];
    $learn = [];
    foreach (cf_learn_tracks_meta() as $id => $m) {
        $t = $counts[$id] ?? 0;
        $d = min($done[$id] ?? 0, $t);
        $status = $d === 0 ? 'not started' : ($d >= $t ? 'complete ✓' : 'ongoing');
        $learn[] = ['id' => $id, 'name' => $m['name'], 'done' => $d, 'total' => $t, 'status' => $status];
    }
    usort($learn, function (array $a, array $b) {
        $rank = fn($s) => ['complete ✓' => 0, 'ongoing' => 1, 'not started' => 2][$s] ?? 3;
        $ra = $rank($a['status']); $rb = $rank($b['status']);
        if ($ra !== $rb) return $ra <=> $rb;
        $pa = $a['total'] ? $a['done'] / $a['total'] : 0;
        $pb = $b['total'] ? $b['done'] / $b['total'] : 0;
        return $pa === $pb ? strcasecmp($a['name'], $b['name']) : ($pb <=> $pa);
    });
    $j['learn'] = $learn;
    return $j;
}

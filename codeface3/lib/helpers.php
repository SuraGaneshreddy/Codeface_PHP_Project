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

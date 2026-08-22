<?php
declare(strict_types=1);

function current_user(): ?array {
    static $loaded = false, $user = null;
    if ($loaded) return $user;
    $loaded = true;
    if (empty($_SESSION['uid'])) return null;
    $user = db_one(
        'SELECT id, username, email, display_name, bio, avatar, avatar_color, rating, is_admin, created_at, last_seen
         FROM users WHERE id = ?',
        [(int)$_SESSION['uid']]
    );
    if (!$user) unset($_SESSION['uid']);
    return $user;
}

function require_login(): array {
    $u = current_user();
    if (!$u) {
        $here = basename($_SERVER['PHP_SELF'] ?? 'index.php');
        $qs   = $_SERVER['QUERY_STRING'] ?? '';
        redirect('login.php?next=' . urlencode($here . ($qs ? '?' . $qs : '')));
    }
    return $u;
}

function require_login_json(): array {
    $u = current_user();
    if (!$u) json_error('You must be signed in.', 401);
    return $u;
}

/** Attempt login by username OR email. Returns the user array or null. */
function login_attempt(string $identity, string $password): ?array {
    $row = db_one(
        'SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1',
        [$identity, $identity]
    );
    if (!$row || !password_verify($password, $row['password_hash'])) return null;
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];
    $_SESSION['_seen'] = time();
    return $row;
}

/**
 * Register a new user. Throws RuntimeException with a human message on validation failure.
 * Returns the new user id.
 */
/**
 * Validate + register a new user from a PLAIN password.
 * Throws RuntimeException with a human message on validation failure.
 */
function register_user(string $username, string $email, string $password): int {
    $username = trim($username);
    $email    = trim($email);

    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        throw new RuntimeException('Username must be 3–20 characters: letters, numbers, underscores only.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('That email address does not look valid.');
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('Password must be at least 8 characters.');
    }
    return create_user_account($username, $email, password_hash($password, PASSWORD_DEFAULT));
}

/** Insert the account with a PRE-HASHED password (used after email verification,
 *  where only the hash was parked in the session — the plaintext never lingers). */
function create_user_account(string $username, string $email, string $passwordHash): int {
    if (db_one('SELECT id FROM users WHERE username = ?', [$username])) {
        throw new RuntimeException('That username is taken.');
    }
    if (db_one('SELECT id FROM users WHERE email = ?', [$email])) {
        throw new RuntimeException('An account with that email already exists.');
    }
    $st = db()->prepare(
        'INSERT INTO users (username, email, password_hash, display_name, bio, avatar_color, rating, created_at, last_seen)
         VALUES (?, ?, ?, ?, ?, ?, 1200, ?, ?)'
    );
    $st->execute([
        $username,
        $email,
        $passwordHash,
        $username,
        '',
        random_avatar_color(),
        now(),
        now(),
    ]);
    return (int)db()->lastInsertId();
}

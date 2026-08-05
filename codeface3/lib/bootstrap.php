<?php
declare(strict_types=1);

/* Session first — before any output. */
if (session_status() === PHP_SESSION_NONE) {
    session_name('CODEFACESESSID');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/helpers.php';
require __DIR__ . '/langs.php';
require __DIR__ . '/emitters.php';
require __DIR__ . '/learnbank.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/rooms.php';

date_default_timezone_set('UTC');

/* Light-touch "last seen" for the signed-in user (throttled to 1 write / 30s). */
if (!empty($_SESSION['uid'])) {
    $seen = (int)($_SESSION['_seen'] ?? 0);
    if (time() - $seen > 30) {
        $_SESSION['_seen'] = time();
        try {
            $st = db()->prepare('UPDATE users SET last_seen = ? WHERE id = ?');
            $st->execute([now(), (int)$_SESSION['uid']]);
        } catch (Throwable $e) { /* never break a request over presence */ }
    }
}

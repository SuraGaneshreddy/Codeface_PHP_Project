<?php
declare(strict_types=1);

/**
 * Forgot-password flow: 6-digit OTP delivered by email, valid 10 minutes,
 * stored hashed, max 5 verification attempts, one resend per 60 seconds.
 * The request endpoint never reveals whether an email has an account.
 */

require_once __DIR__ . '/mailer.php';

/** Create + email a fresh OTP for the account owning $email (silent no-op if none). */
function cf_password_reset_request(string $email): bool {
    $user = db_one('SELECT id, username, email FROM users WHERE email = ?', [$email]);
    if (!$user) return true;                      // unknown email → pretend success (no enumeration)
    $uid = (int)$user['id'];

    // resend throttle: at most one fresh code per minute per account
    $last = db_one('SELECT created_at FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$uid]);
    if ($last && strtotime((string)$last['created_at']) > time() - 60) return true;

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    db()->prepare('INSERT INTO password_resets (user_id, otp_hash, expires_at, created_at) VALUES (?, ?, ?, ?)')
        ->execute([$uid, password_hash($otp, PASSWORD_DEFAULT), ts(time() + 600), now()]);

    global $config;
    $app = $config['app']['name'] ?? 'Codeface';
    $body = "Hi {$user['username']},\n\n"
          . "Someone (hopefully you) asked to reset your $app password.\n\n"
          . "Your one-time verification code is:\n\n"
          . "    $otp\n\n"
          . "It expires in 10 minutes. If this wasn't you, simply ignore this email —\n"
          . "your password stays unchanged.\n\n"
          . "— the $app team";
    return cf_mail_send($user['email'], "$app password reset code: $otp", $body);
}

/** Verify the OTP and set a new password. Throws RuntimeException with a safe message. */
function cf_password_reset_verify(string $email, string $otp, string $newPassword): void {
    if (mb_strlen($newPassword) < 8) {
        throw new RuntimeException('Choose a new password of at least 8 characters.');
    }
    $fail = 'That code is not valid or has expired. Request a new one.';
    $user = db_one('SELECT id FROM users WHERE email = ?', [$email]);
    if (!$user) throw new RuntimeException($fail);

    $row = db_one(
        'SELECT id, otp_hash, expires_at, attempts FROM password_resets
         WHERE user_id = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1',
        [(int)$user['id']]
    );
    if (!$row) throw new RuntimeException($fail);
    if (strtotime((string)$row['expires_at']) < time()) throw new RuntimeException($fail);
    if ((int)$row['attempts'] >= 5) throw new RuntimeException('Too many wrong codes — request a fresh one.');

    // count every check so brute-forcing 6 digits is infeasible
    db()->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')->execute([(int)$row['id']]);

    if (!preg_match('/^\d{6}$/', $otp) || !password_verify($otp, (string)$row['otp_hash'])) {
        throw new RuntimeException($fail);
    }

    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int)$user['id']]);
    db()->prepare('UPDATE password_resets SET used_at = ? WHERE id = ?')->execute([now(), (int)$row['id']]);
}

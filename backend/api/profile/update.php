<?php
/* Update own display name + bio (form POST, redirect back to profile). */
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login();
require_method('POST');
verify_csrf();

$display = trim((string)($_POST['display_name'] ?? ''));
$bio     = trim((string)($_POST['bio'] ?? ''));
if (mb_strlen($display) > 40) $display = mb_substr($display, 0, 40);
if (mb_strlen($bio) > 220)    $bio = mb_substr($bio, 0, 220);
if ($display !== '' && !preg_match('/^[\p{L}\p{N} ._\-\']+$/u', $display)) {
    $_SESSION['flash_profile'] = ['type' => 'error', 'text' => 'Display name has characters we can’t show — letters, numbers, spaces and . _ - \' only.'];
} else {
    db()->prepare('UPDATE users SET display_name = ?, bio = ? WHERE id = ?')
        ->execute([$display, $bio, (int)$me['id']]);
    $_SESSION['flash_profile'] = ['type' => 'success', 'text' => 'Profile updated.'];
}
redirect('../../../frontend/profile.php?u=' . urlencode($me['username']));

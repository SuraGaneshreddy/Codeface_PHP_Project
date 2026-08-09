<?php
/* Upload own profile photo (multipart POST, redirect back to profile).
 * Images land in database/data/avatars/ and are served via frontend/avatar.php (web-denied dir).
 * No GD dependency: original file kept as-is; CSS handles presentation (object-fit). */
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login();
require_method('POST');
verify_csrf();

$back = '../../../frontend/profile.php?u=' . urlencode($me['username']);
$fail = function (string $msg) use ($back) {
    $_SESSION['flash_profile'] = ['type' => 'error', 'text' => $msg];
    redirect($back);
};

$f = $_FILES['avatar'] ?? null;
if (!$f || !is_uploaded_file($f['tmp_name'] ?? '')) $fail('Choose an image first.');
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) $fail('Upload didn’t complete — try again.');
if (filesize($f['tmp_name']) > 2 * 1024 * 1024) $fail('That image is over 2 MB — crop or compress it first.');

$mime  = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
$extBy = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
if (!isset($extBy[$mime])) $fail('Only JPG, PNG, GIF or WebP images, please.');

/* quick sanity via getimagesize (works without GD) */
if (@getimagesize($f['tmp_name']) === false) $fail('That file doesn’t look like a real image.');

$dir = __DIR__ . '/../../../database/data/avatars';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$id = (int)$me['id'];
foreach (glob($dir . "/u{$id}.*") ?: [] as $old) @unlink($old);
$fname = "u{$id}." . $extBy[$mime];
if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $fname)) $fail('Couldn’t store the file (permissions?).');

db()->prepare('UPDATE users SET avatar = ? WHERE id = ?')->execute([$fname, $id]);
$_SESSION['flash_profile'] = ['type' => 'success', 'text' => 'Profile photo updated.'];
redirect($back);

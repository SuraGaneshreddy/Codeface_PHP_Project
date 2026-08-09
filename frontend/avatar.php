<?php
/* Streams a user's uploaded avatar. database/data/ is web-denied (sqlite lives there),
 * so profile photos are served through this tiny passthrough instead. */
require __DIR__ . '/../backend/lib/bootstrap.php';

$id  = (int)($_GET['id'] ?? 0);
$row = $id > 0 ? db_one('SELECT avatar FROM users WHERE id = ?', [$id]) : null;
$file = $row ? cf_avatar_file($row['avatar'] ?? null) : null;
if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'no avatar';
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
if (strpos($mime, 'image/') !== 0) { http_response_code(404); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file)) . ' GMT');
readfile($file);
exit;

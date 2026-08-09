<?php
/**
 * Server-Sent Events stream for a room.
 * One connection per room tab; emits:
 *   event: snapshot  — full room state (sent once on connect)
 *   event: code      — a pad changed {language, version, content, editor}
 *   event: chat      — a new chat message {id, username, color, body, created_at}
 *   event: presence  — member list changed {members:[...]}
 *   event: bye       — server is closing; EventSource will reconnect and get a fresh snapshot
 */
require __DIR__ . '/../../lib/bootstrap.php';

$me = require_login_json();

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
$room = $code !== '' ? room_by_code($code) : null;
if (!$room) json_error('Room not found.', 404);

$roomId = (int)$room['id'];
$userId = (int)$me['id'];
room_join($roomId, $userId, $room['owner_username'] === $me['username'] ? 'owner' : 'participant');

/* Release the PHP session lock — a long-lived stream would otherwise block
   every other request from this user (heartbeat, chat, push…). */
session_write_close();

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
while (ob_get_level() > 0) { @ob_end_clean(); }

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');
header('Connection: keep-alive');

function sse_send(string $event, $data): void {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    if (ob_get_level() > 0) @ob_flush();
    flush();
}

$maxSeconds = (int)($config['sse']['max_seconds'] ?? 50);
$tickUs     = (int)($config['sse']['tick_ms'] ?? 600) * 1000;

// --- initial snapshot -------------------------------------------------------
$payload = room_state_payload($room, $userId);
sse_send('snapshot', $payload);

$padVersions = [];
foreach ($payload['pads'] as $lang => $pad) $padVersions[$lang] = (int)$pad['version'];
$chatCursor = 0;
foreach ($payload['chat'] as $m) $chatCursor = max($chatCursor, (int)$m['id']);
$presenceSig = '';
$tick = 0;
$start = time();

while (true) {
    if (connection_aborted()) break;
    if ((time() - $start) >= $maxSeconds) { sse_send('bye', ['reason' => 'rotate']); break; }

    usleep($tickUs);
    $tick++;

    // pads
    foreach (room_pads($roomId) as $lang => $pad) {
        $known = $padVersions[$lang] ?? -1;
        if ((int)$pad['version'] > $known) {
            $padVersions[$lang] = (int)$pad['version'];
            sse_send('code', [
                'language' => $lang,
                'version'  => (int)$pad['version'],
                'content'  => $pad['content'],
                'editor'   => $pad['editor'],
            ]);
        }
    }

    // chat
    $rows = db_all(
        'SELECT c.id, c.body, c.created_at, u.username, u.avatar_color
         FROM chat_messages c LEFT JOIN users u ON u.id = c.user_id
         WHERE c.room_id = ? AND c.id > ? ORDER BY c.id ASC LIMIT 100',
        [$roomId, $chatCursor]
    );
    foreach ($rows as $r) {
        $chatCursor = (int)$r['id'];
        sse_send('chat', [
            'id'         => (int)$r['id'],
            'username'   => $r['username'] ?? 'ghost',
            'color'      => $r['avatar_color'] ?? '#888',
            'body'       => $r['body'],
            'created_at' => $r['created_at'],
        ]);
    }

    // presence (only when something changed)
    $members = room_members($roomId);
    $sig = implode(',', array_map(function ($m) { return $m['username'] . ':' . ($m['online'] ? '1' : '0'); }, $members));
    if ($sig !== $presenceSig) {
        $presenceSig = $sig;
        sse_send('presence', ['members' => $members]);
    }

    // keep-alive comment for proxies/browsers (~every 15s)
    if ($tick % 25 === 0) { echo ": ping\n\n"; flush(); }

    if ($tick % 8 === 0 && connection_aborted()) break;
}
exit;

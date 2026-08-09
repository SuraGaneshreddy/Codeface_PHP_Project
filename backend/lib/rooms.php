<?php
declare(strict_types=1);

/** Shared room queries used by the room page, the JSON API and the SSE stream. */

const ROOM_ONLINE_SECONDS = 15;
/* ROOM_LANGUAGES (all 12 languages) is defined by lib/langs.php, loaded earlier. */

function room_by_code(string $code): ?array {
    return db_one(
        'SELECT r.*, u.username AS owner_username, p.slug AS problem_slug, p.title AS problem_title, p.difficulty AS problem_difficulty
         FROM rooms r
         LEFT JOIN users u ON u.id = r.owner_id
         LEFT JOIN problems p ON p.id = r.problem_id
         WHERE r.code = ?',
        [strtoupper(trim($code))]
    );
}

function room_pads(int $roomId): array {
    $rows = db_all('SELECT language, content, version, last_editor_id FROM room_pads WHERE room_id = ?', [$roomId]);
    $pads = [];
    foreach ($rows as $r) {
        $editorName = null;
        if (!empty($r['last_editor_id'])) {
            $ed = db_one('SELECT username FROM users WHERE id = ?', [(int)$r['last_editor_id']]);
            $editorName = $ed['username'] ?? null;
        }
        $pads[$r['language']] = [
            'content' => $r['content'],
            'version' => (int)$r['version'],
            'editor'  => $editorName,
        ];
    }
    return $pads;
}

function room_members(int $roomId): array {
    $rows = db_all(
        'SELECT m.user_id, m.role, m.joined_at, m.last_seen, m.left_at,
                u.username, u.display_name, u.avatar_color, u.rating
         FROM room_members m JOIN users u ON u.id = m.user_id
         WHERE m.room_id = ? AND m.left_at IS NULL
         ORDER BY m.joined_at ASC',
        [$roomId]
    );
    $cutoff = time() - ROOM_ONLINE_SECONDS;
    return array_map(function ($m) use ($cutoff) {
        $seen = $m['last_seen'] ? strtotime($m['last_seen'] . ' UTC') : 0;
        return [
            'id'           => (int)$m['user_id'],
            'username'     => $m['username'],
            'display_name' => $m['display_name'],
            'avatar_color' => $m['avatar_color'],
            'rating'       => (int)$m['rating'],
            'role'         => $m['role'],
            'online'       => $seen >= $cutoff,
        ];
    }, $rows);
}

function room_chat_recent(int $roomId, int $limit = 30): array {
    $rows = db_all(
        'SELECT c.id, c.body, c.created_at, u.username, u.avatar_color
         FROM chat_messages c LEFT JOIN users u ON u.id = c.user_id
         WHERE c.room_id = ? ORDER BY c.id DESC LIMIT ' . (int)$limit,
        [$roomId]
    );
    return array_reverse(array_map(function ($r) {
        return [
            'id'         => (int)$r['id'],
            'username'   => $r['username'] ?? 'ghost',
            'color'      => $r['avatar_color'] ?? '#888',
            'body'       => $r['body'],
            'created_at' => $r['created_at'],
        ];
    }, $rows));
}

function room_join(int $roomId, int $userId, string $role = 'participant'): void {
    $pdo = db();
    $st = $pdo->prepare('SELECT user_id, left_at FROM room_members WHERE room_id = ? AND user_id = ?');
    $st->execute([$roomId, $userId]);
    $existing = $st->fetch();
    if ($existing) {
        $up = $pdo->prepare('UPDATE room_members SET left_at = NULL, last_seen = ? WHERE room_id = ? AND user_id = ?');
        $up->execute([now(), $roomId, $userId]);
    } else {
        $ins = $pdo->prepare('INSERT INTO room_members (room_id, user_id, role, joined_at, last_seen) VALUES (?, ?, ?, ?, ?)');
        $ins->execute([$roomId, $userId, $role, now(), now()]);
    }
}

function room_touch_member(int $roomId, int $userId): void {
    $st = db()->prepare('UPDATE room_members SET last_seen = ? WHERE room_id = ? AND user_id = ?');
    $st->execute([now(), $roomId, $userId]);
}

function room_leave(int $roomId, int $userId): void {
    $st = db()->prepare('UPDATE room_members SET left_at = ? WHERE room_id = ? AND user_id = ?');
    $st->execute([now(), $roomId, $userId]);
}

function room_comment(string $lang, string $text): string {
    $prefixes = ['python' => '# ', 'ruby' => '# ', 'php' => '// '];
    return ($prefixes[$lang] ?? '// ') . $text;
}

/** Default starter pads for a new room — one pad per supported language. */
function room_default_pads(?array $problem): array {
    $pads = [];
    $starters = [];
    if ($problem) {
        $starters = json_decode($problem['starters_json'] ?? '', true) ?: [];
    }
    foreach (cf_languages() as $id => $m) {
        if ($problem && isset($starters[$id])) {
            $pads[$id] = $starters[$id];
        } elseif ($problem && $id === 'javascript') {
            $pads[$id] = $problem['starter_js'];
        } else {
            $pads[$id] = room_comment($id, "Welcome to your Codeface room! ({$m['name']} pad)")
                . "\n" . room_comment($id, "Share the room code, pick a problem, and pair.") . "\n\n";
        }
    }
    return $pads;
}

/** Full state payload — single source used by state.php and by the SSE snapshot. */
function room_state_payload(array $room, int $userId): array {
    return [
        'room' => [
            'code'     => $room['code'],
            'name'     => $room['name'],
            'language' => $room['language'],
            'is_live'  => (bool)$room['is_live'],
            'owner'    => $room['owner_username'],
            'problem'  => $room['problem_id'] ? [
                'slug'       => $room['problem_slug'],
                'title'      => $room['problem_title'],
                'difficulty' => $room['problem_difficulty'],
            ] : null,
        ],
        'pads'    => room_pads((int)$room['id']),
        'members' => room_members((int)$room['id']),
        'chat'    => room_chat_recent((int)$room['id'], 30),
        'you'     => $userId,
    ];
}

<?php
declare(strict_types=1);

/**
 * Learn content registry — V5.
 * 15 tracks × 16 practical lessons each (240 lessons total), split across
 * four sequential levels (4 lessons per level):
 *   🌱 Beginner (1–4) → 🌿 Intermediate (5–8) → 🌳 Advance (9–12) → 🏆 Pro (13–16)
 * Levels unlock in order per user: finish all 4 lessons of a level to open
 * the next one (enforced server-side in the learn pages).
 *   - the 12 practice languages (js, ts, python, java, c, cpp, csharp, go,
 *     ruby, php, kotlin, rust)
 *   - 3 learn-only tracks: SQL, Bash (shell), HTML & CSS
 * Every lesson is anchored in a real-world scenario (carts, logs, expense
 * reports — never foo/bar).
 *
 * Lesson shapes (helper L below):
 *   concept  — short HTML explanation
 *   example  — idiomatic code sample for that language
 *   output   — what running the example prints (shown for all languages)
 *   try      — a runnable variant (JS + Python tracks only) the student can edit & run
 *   problem  — a linked Codeface practice problem (slug)
 */

function L(string $slug, string $title, int $minutes, string $concept,
           string $example, string $output = '', string $try = '', string $problem = ''): array {
    return [
        'slug' => $slug, 'title' => $title, 'minutes' => $minutes,
        'concept' => $concept, 'example' => $example, 'output' => $output,
        'try' => $try, 'problem' => $problem,
    ];
}

/**
 * Everything about every track's lessons: [trackId => ['lessons' => [...]]].
 * Files with 1..N parts; a track can span files (later parts append lessons).
 */
function cf_learn_tracks(): array {
    static $tracks = null;
    if ($tracks !== null) return $tracks;
    $tracks = [];
    foreach (['tracks1.php', 'tracks2.php', 'tracks3.php', 'tracks4.php',
              'tracks5.php', 'tracks6.php'] as $file) {
        $part = require __DIR__ . '/learnbank/' . $file;
        foreach ($part as $id => $t) {
            if (!isset($tracks[$id])) $tracks[$id] = ['lessons' => []];
            $tracks[$id]['lessons'] = array_merge($tracks[$id]['lessons'], $t['lessons'] ?? []);
        }
    }
    return $tracks;
}

/** Learn-only tracks (not practice languages): same meta shape as cf_languages(). */
function cf_learn_extra_meta(): array {
    return [
        'sql' => [
            'name' => 'SQL', 'monaco' => 'sql', 'runner' => null, 'case' => 'snake',
            'blurb' => 'The universal language of data. Four keywords — SELECT, FROM, WHERE, JOIN — run every app you have ever used.',
        ],
        'bash' => [
            'name' => 'Bash (shell)', 'monaco' => 'shell', 'runner' => null, 'case' => 'snake',
            'blurb' => 'The glue of every server on Earth. Tiny scripts that move mountains of files, logs, and deploys.',
        ],
        'htmlcss' => [
            'name' => 'HTML & CSS', 'monaco' => 'html', 'runner' => null, 'case' => 'snake',
            'blurb' => 'Structure and style — the two notations every screen on the internet is built with.',
        ],
    ];
}

/** Every learn track: 12 practice languages + learn-only extras. */
function cf_learn_tracks_meta(?string $id = null) {
    static $all = null;
    if ($all === null) $all = cf_languages() + cf_learn_extra_meta();
    return $id === null ? $all : ($all[$id] ?? null);
}

/** Card metadata for the Learn landing page. */
function cf_learn_card(string $lang): array {
    $meta = [
        'javascript' => ['level' => 'Beginner', 'hook' => 'Zero setup, instant results — every browser is an IDE.'],
        'typescript' => ['level' => 'Beginner→Pro', 'hook' => 'Learn the safety net professional JS teams rely on.'],
        'python'     => ['level' => 'Beginner', 'hook' => 'The friendliest first language — automation, data, glue code.'],
        'java'       => ['level' => 'Intermediate', 'hook' => 'The interview language of enterprise engineering.'],
        'c'          => ['level' => 'Intermediate', 'hook' => 'Understand what every other language hides from you.'],
        'cpp'        => ['level' => 'Intermediate', 'hook' => 'Competitive programming and game engines speak this.'],
        'csharp'     => ['level' => 'Intermediate', 'hook' => 'From desktop apps to Unity games with one elegant language.'],
        'go'         => ['level' => 'Beginner→Pro', 'hook' => 'The DevOps/cloud native lingua franca. Tiny language, huge leverage.'],
        'ruby'       => ['level' => 'Beginner', 'hook' => 'Reads like English — the fastest path to feeling fluent.'],
        'php'        => ['level' => 'Beginner', 'hook' => 'Learn the language this very app is written in.'],
        'kotlin'     => ['level' => 'Intermediate', 'hook' => 'Android careers start here — modern and pragmatic.'],
        'rust'       => ['level' => 'Advanced', 'hook' => 'Most-loved language for a decade. Stretch your brain.'],
        'sql'        => ['level' => 'Beginner', 'hook' => 'The one skill every backend, analyst, and founder shares.'],
        'bash'       => ['level' => 'Beginner→Pro', 'hook' => 'Automate the boring 80% of ops and file wrangling.'],
        'htmlcss'    => ['level' => 'Beginner', 'hook' => 'Watch your first real web page come alive in minutes.'],
    ];
    return $meta[$lang] ?? ['level' => '', 'hook' => ''];
}

/** The four difficulty levels inside every Learn track (positions map 1:1). */
function cf_learn_levels(): array {
    return [
        'beginner' => [
            'name' => 'Beginner', 'range' => [1, 4], 'icon' => '🌱',
            'blurb' => 'Start from zero: values, variables, conditions and loops — your first programs that actually do something.',
        ],
        'intermediate' => [
            'name' => 'Intermediate', 'range' => [5, 8], 'icon' => '🌿',
            'blurb' => 'Level up: collections, strings and the everyday text/data pipelines real jobs run on.',
        ],
        'advance' => [
            'name' => 'Advance', 'range' => [9, 12], 'icon' => '🌳',
            'blurb' => 'Think like an engineer: structure, abstractions, file I/O and the idioms that separate hobby code from pro code.',
        ],
        'pro' => [
            'name' => 'Pro', 'range' => [13, 16], 'icon' => '🏆',
            'blurb' => 'Prove it: production patterns, testing, tooling and a capstone mini-project that assembles the whole track, end to end.',
        ],
    ];
}

/** Map a lesson position (1..16) to its level slug. */
function cf_learn_level_of_position(int $pos): string {
    foreach (cf_learn_levels() as $slug => $lv) {
        if ($pos >= $lv['range'][0] && $pos <= $lv['range'][1]) return $slug;
    }
    return 'beginner';
}

/** Level slugs in unlock order. */
function cf_learn_level_order(): array {
    return ['beginner', 'intermediate', 'advance', 'pro'];
}

/**
 * Per-user progress in one level of a track.
 * Returns ['done' => int, 'total' => int, 'complete' => bool].
 * Guests (null user id) always have 0 done → every non-beginner level stays locked.
 */
function cf_learn_level_progress(string $track, string $levelSlug, ?int $uid): array {
    $lv = cf_learn_levels()[$levelSlug] ?? null;
    if (!$lv) return ['done' => 0, 'total' => 0, 'complete' => false];
    [$a, $b] = $lv['range'];
    $total = (int)(db_one(
        'SELECT COUNT(*) AS c FROM learn_lessons WHERE track = ? AND position BETWEEN ? AND ?',
        [$track, $a, $b]
    )['c'] ?? 0);
    $done = 0;
    if ($uid) {
        $done = (int)(db_one(
            'SELECT COUNT(*) AS c FROM learn_progress p
               JOIN learn_lessons l ON l.id = p.lesson_id
              WHERE p.user_id = ? AND l.track = ? AND l.position BETWEEN ? AND ?',
            [$uid, $track, $a, $b]
        )['c'] ?? 0);
    }
    return ['done' => $done, 'total' => $total, 'complete' => $total > 0 && $done >= $total];
}

/**
 * Hard level locking: Beginner is always open; every other level requires the
 * previous level to be fully complete (all its lessons done) by THIS user.
 */
function cf_learn_level_unlocked(string $track, string $levelSlug, ?int $uid): bool {
    $order = cf_learn_level_order();
    $i = array_search($levelSlug, $order, true);
    if ($i === false || $i === 0) return true; // beginner + unknown
    return cf_learn_level_progress($track, $order[$i - 1], $uid)['complete'];
}

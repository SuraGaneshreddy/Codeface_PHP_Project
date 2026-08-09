<?php
declare(strict_types=1);

/**
 * Versioned content seeding: problems (from lib/pbank/*.php) + Learn lessons
 * (from lib/learnbank.php). Idempotent — problems upsert by slug, lessons by
 * (track, slug), so upgrading the app adds/refreshes content without touching
 * user accounts, submissions, rooms or ratings.
 */

/*  v1: base schema. v2: + categories, per-language starters, learn tables.
    v3: corrected content (fixed test expectations & reference solutions).
    v4: problem bank grown to 526 (+ a new real-world category).
    v5: Learn section deepened — 8 lessons per track (96 core) plus three
        new learn-only tracks: SQL, Bash, HTML & CSS (120 lessons total).
    v6: Pro Mode — lab_progress + refactor_runs.
    v7: Learn expanded to 16 lessons/track (240 total) across 4 sequential,
        server-side-locked levels (🌱 1–4, 🌿 5–8, 🌳 9–12, 🏆 13–16).
    v8: Hackathon section removed (tables dropped); problems.ai_user_id added
        for per-user 🤖 AI-generated problem sets.
    v9: users.avatar — uploaded profile photo filename (initials fallback). */
const CF_SCHEMA_VERSION = 9; // v9: users.avatar (profile photo upload)

/** Compact problem-spec builder used across lib/pbank files. */
function pdef(string $slug, string $title, string $difficulty, string $fn, array $sig,
              string $blurb, array $constraints, array $tests, string $solution, string $follow = ''): array {
    return [
        'slug'        => $slug,
        'title'       => $title,
        'difficulty'  => $difficulty,
        'fn'          => $fn,
        'sig'         => $sig,
        'description' => cf_build_description($fn, $sig, $blurb, $constraints, $tests, $follow),
        'tests'       => $tests,
        'solution_js' => $solution,
    ];
}

/** Load every problem spec in lib/pbank/*.php, keyed by slug. Category = file name. */
function cf_problem_bank(): array {
    static $bank = null;
    if ($bank !== null) return $bank;
    require_once __DIR__ . '/emitters.php';
    $bank = [];
    foreach (glob(__DIR__ . '/pbank/*.php') as $file) {
        $cat = basename($file, '.php');
        foreach ((require $file) as $spec) {
            $spec['category'] = $spec['category'] ?? $cat;
            $bank[$spec['slug']] = $spec;
        }
    }
    return $bank;
}

function cf_seed_content(PDO $pdo): void {
    require_once __DIR__ . '/emitters.php';
    require_once __DIR__ . '/learnbank.php';
    $mysql = db_driver() === 'mysql';

    // ---------- problems: upsert by slug ----------
    $cols = 'slug, title, difficulty, description, tags, function_name, starter_js, solution_js, tests_json, points, category, starters_json';
    if ($mysql) {
        $sql = "INSERT INTO problems ({$cols}) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE title=VALUES(title), difficulty=VALUES(difficulty), description=VALUES(description),
                    tags=VALUES(tags), function_name=VALUES(function_name), starter_js=VALUES(starter_js),
                    solution_js=VALUES(solution_js), tests_json=VALUES(tests_json), points=VALUES(points),
                    category=VALUES(category), starters_json=VALUES(starters_json)";
    } else {
        $sql = "INSERT INTO problems ({$cols}) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON CONFLICT(slug) DO UPDATE SET title=excluded.title, difficulty=excluded.difficulty,
                    description=excluded.description, tags=excluded.tags, function_name=excluded.function_name,
                    starter_js=excluded.starter_js, solution_js=excluded.solution_js, tests_json=excluded.tests_json,
                    points=excluded.points, category=excluded.category, starters_json=excluded.starters_json";
    }
    $st = $pdo->prepare($sql);
    $points = ['easy' => 10, 'medium' => 20, 'hard' => 35];
    foreach (cf_problem_bank() as $p) {
        $starters = cf_starters_all($p['fn'], $p['sig']);
        $st->execute([
            $p['slug'], $p['title'], $p['difficulty'], $p['description'],
            $p['category'] . ',practice', $p['fn'], $starters['javascript'], $p['solution_js'],
            json_encode($p['tests'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $points[$p['difficulty']] ?? 10, $p['category'],
            json_encode($starters, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    // ---------- learn lessons: upsert by (track, slug), prune stale ----------
    $lcols = 'track, position, slug, title, concept, example_code, example_output, try_code, problem_slug, minutes';
    if ($mysql) {
        $lsql = "INSERT INTO learn_lessons ({$lcols}) VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE position=VALUES(position), title=VALUES(title), concept=VALUES(concept),
                     example_code=VALUES(example_code), example_output=VALUES(example_output), try_code=VALUES(try_code),
                     problem_slug=VALUES(problem_slug), minutes=VALUES(minutes)";
    } else {
        $lsql = "INSERT INTO learn_lessons ({$lcols}) VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON CONFLICT(track, slug) DO UPDATE SET position=excluded.position, title=excluded.title,
                     concept=excluded.concept, example_code=excluded.example_code, example_output=excluded.example_output,
                     try_code=excluded.try_code, problem_slug=excluded.problem_slug, minutes=excluded.minutes";
    }
    $lst = $pdo->prepare($lsql);
    foreach (cf_learn_tracks() as $trackId => $track) {
        $pos = 0;
        $slugs = [];
        foreach ($track['lessons'] as $l) {
            $pos++;
            $slugs[] = $l['slug'];
            $lst->execute([
                $trackId, $pos, $l['slug'], $l['title'], $l['concept'], $l['example'] ?? '',
                $l['output'] ?? '', $l['try'] ?? '', $l['problem'] ?? '', $l['minutes'] ?? 6,
            ]);
        }
        if ($slugs) {
            $ph = implode(',', array_fill(0, count($slugs), '?'));
            $del = $pdo->prepare("DELETE FROM learn_lessons WHERE track = ? AND slug NOT IN ({$ph})");
            $del->execute(array_merge([$trackId], $slugs));
        }
    }
}

/** meta table helpers */
function cf_meta_get(PDO $pdo, string $key): ?string {
    try {
        $row = $pdo->prepare('SELECT value FROM meta WHERE `key` = ?');
        $row->execute([$key]);
        $r = $row->fetch();
        return $r ? (string)$r['value'] : null;
    } catch (Throwable $e) {
        return null;
    }
}

function cf_meta_set(PDO $pdo, string $key, string $value): void {
    if (db_driver() === 'mysql') {
        $pdo->prepare('INSERT INTO meta (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)')
            ->execute([$key, $value]);
    } else {
        $pdo->prepare('INSERT INTO meta (`key`, `value`) VALUES (?, ?) ON CONFLICT(`key`) DO UPDATE SET `value` = excluded.`value`')
            ->execute([$key, $value]);
    }
}

/** Bring an older database to the current schema, then refresh content. */
function cf_migrate_and_seed(PDO $pdo): void {
    require_once __DIR__ . '/content_seed.php';
    if ((int)(cf_meta_get($pdo, 'schema_version') ?? '0') >= CF_SCHEMA_VERSION) return;

    $mysql = db_driver() === 'mysql';
    // v1 → v2: widen problems table (ignore "duplicate column" on fresh schemas)
    try { $pdo->exec("ALTER TABLE problems ADD COLUMN category " . ($mysql ? "VARCHAR(40) NOT NULL DEFAULT ''" : "TEXT NOT NULL DEFAULT ''")); } catch (Throwable $e) {}
    try { $pdo->exec('ALTER TABLE problems ADD COLUMN starters_json ' . ($mysql ? 'MEDIUMTEXT NULL' : 'TEXT NULL')); } catch (Throwable $e) {}

    if ($mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta (`key` VARCHAR(40) PRIMARY KEY, `value` VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS learn_lessons (
              id INT AUTO_INCREMENT PRIMARY KEY, track VARCHAR(20) NOT NULL, position INT NOT NULL, slug VARCHAR(60) NOT NULL,
              title VARCHAR(160) NOT NULL, concept MEDIUMTEXT NOT NULL, example_code MEDIUMTEXT NOT NULL,
              example_output MEDIUMTEXT NOT NULL, try_code MEDIUMTEXT NOT NULL, problem_slug VARCHAR(80) NOT NULL DEFAULT '',
              minutes INT NOT NULL DEFAULT 5, UNIQUE KEY uq_lesson (track, slug), INDEX idx_lessons_track (track, position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS learn_progress (
              user_id INT NOT NULL, lesson_id INT NOT NULL, completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (user_id, lesson_id),
              CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
              CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES learn_lessons(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS learn_lessons (
              id INTEGER PRIMARY KEY AUTOINCREMENT, track TEXT NOT NULL, position INTEGER NOT NULL, slug TEXT NOT NULL,
              title TEXT NOT NULL, concept TEXT NOT NULL, example_code TEXT NOT NULL DEFAULT '',
              example_output TEXT NOT NULL DEFAULT '', try_code TEXT NOT NULL DEFAULT '',
              problem_slug TEXT NOT NULL DEFAULT '', minutes INTEGER NOT NULL DEFAULT 5, UNIQUE(track, slug))");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lessons_track ON learn_lessons(track, position)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS learn_progress (
              user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              lesson_id INTEGER NOT NULL REFERENCES learn_lessons(id) ON DELETE CASCADE,
              completed_at TEXT NOT NULL DEFAULT (datetime('now')), PRIMARY KEY (user_id, lesson_id))");
    }

    // v5 → v6: Pro Mode tables (idempotent on every driver)
    if ($mysql) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lab_progress (
              user_id INT NOT NULL, lab_slug VARCHAR(60) NOT NULL,
              completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, lab_slug),
              CONSTRAINT fk_labp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS refactor_runs (
              id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, challenge_slug VARCHAR(60) NOT NULL,
              score INT NOT NULL DEFAULT 0, tests_passed INT NOT NULL DEFAULT 0, tests_total INT NOT NULL DEFAULT 0,
              metrics MEDIUMTEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              INDEX idx_rr_user (user_id), INDEX idx_rr_chal (challenge_slug),
              CONSTRAINT fk_rr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS lab_progress (
              user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, lab_slug TEXT NOT NULL,
              completed_at TEXT NOT NULL DEFAULT (datetime('now')), PRIMARY KEY (user_id, lab_slug))");
        $pdo->exec("CREATE TABLE IF NOT EXISTS refactor_runs (
              id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              challenge_slug TEXT NOT NULL, score INTEGER NOT NULL DEFAULT 0,
              tests_passed INTEGER NOT NULL DEFAULT 0, tests_total INTEGER NOT NULL DEFAULT 0,
              metrics TEXT, created_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rr_user ON refactor_runs(user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rr_chal ON refactor_runs(challenge_slug)');
    }

    // v7 → v8: Hackathon section removed entirely (3 tables dropped);
    // problems gains ai_user_id for per-user 🤖 AI-generated problem rows.
    foreach (['hackathon_problems', 'hackathon_participants', 'hackathons'] as $t) {
        try { $pdo->exec("DROP TABLE IF EXISTS $t"); } catch (Throwable $e) {}
    }
    if ($mysql) {
        try {
            $pdo->exec("ALTER TABLE problems
                ADD COLUMN ai_user_id INT NULL,
                ADD INDEX idx_ai_user (ai_user_id),
                ADD CONSTRAINT fk_prob_aiuser FOREIGN KEY (ai_user_id) REFERENCES users(id) ON DELETE CASCADE");
        } catch (Throwable $e) {}
    } else {
        try { $pdo->exec('ALTER TABLE problems ADD COLUMN ai_user_id INTEGER NULL REFERENCES users(id) ON DELETE CASCADE'); } catch (Throwable $e) {}
        try { $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_user ON problems(ai_user_id)'); } catch (Throwable $e) {}
    }

    // v8 → v9: profile photo uploads (initials + hue circle stays the fallback).
    try { $pdo->exec('ALTER TABLE users ADD COLUMN avatar ' . ($mysql ? 'VARCHAR(40) NULL' : 'TEXT NULL')); } catch (Throwable $e) {}

    cf_seed_content($pdo);
    cf_meta_set($pdo, 'schema_version', (string)CF_SCHEMA_VERSION);
}

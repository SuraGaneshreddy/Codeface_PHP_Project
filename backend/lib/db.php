<?php
declare(strict_types=1);

/**
 * PDO factory with zero-config auto-setup for BOTH drivers:
 *  - sqlite: creates database/data/codeface.sqlite, runs schema, seeds demo data on first use
 *  - mysql:  creates the `codeface` database if missing, runs schema, seeds if empty
 */
function db(): PDO {
    static $pdo = null;
    global $config;
    if ($pdo instanceof PDO) return $pdo;
    require_once __DIR__ . '/seed.php';
    require_once __DIR__ . '/content_seed.php';

    $cfg    = $config['db'];
    $driver = $cfg['driver'];

    if ($driver === 'mysql') {
        $m = $cfg['mysql'];
        $pdo = new PDO(
            "mysql:host={$m['host']};port={$m['port']};charset={$m['charset']}",
            $m['user'],
            $m['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $dbName = preg_replace('/[^A-Za-z0-9_]/', '', (string)($m['name'] ?? 'codeface')) ?: 'codeface';
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");
        $hasUsers = (bool)$pdo->query("SHOW TABLES LIKE 'users'")->fetch();
        if (!$hasUsers) {
            db_run_sql_file($pdo, __DIR__ . '/../../database/schema.mysql.sql');
            seed_database($pdo);
        } else {
            seed_database_if_empty($pdo);
        }
    } else {
        $path  = $cfg['sqlite_path'];
        $fresh = !file_exists($path) || filesize($path) === 0;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($fresh) {
            db_run_sql_file($pdo, __DIR__ . '/../../database/schema.sqlite.sql');
            seed_database($pdo);
        }
    }
    cf_migrate_and_seed($pdo);
    return $pdo;
}

/** Execute a .sql file containing only plain DDL/DML statements (no DELIMITER/procedures). */
function db_run_sql_file(PDO $pdo, string $file): void {
    $sql = file_get_contents($file);
    if ($sql === false) throw new RuntimeException("Cannot read SQL file: {$file}");
    // strip comment lines, then split on semicolons at line ends
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $stmts = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    foreach ($stmts as $st) {
        $st = trim($st);
        if ($st !== '') $pdo->exec($st);
    }
}

function seed_database_if_empty(PDO $pdo): void {
    try {
        $row = $pdo->query('SELECT COUNT(*) AS c FROM users')->fetch();
        if ((int)($row['c'] ?? 0) === 0) seed_database($pdo);
    } catch (Throwable $e) { /* tables may not exist yet */ }
}

function db_driver(): string {
    global $config;
    return (string)($config['db']['driver'] ?? 'sqlite');
}

/** Portable random ordering function. */
function db_random(): string {
    return db_driver() === 'sqlite' ? 'RANDOM()' : 'RAND()';
}

/** Returns one row or null. */
function db_one(string $sql, array $params = []): ?array {
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Returns all rows. */
function db_all(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

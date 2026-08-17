<?php
/**
 * Codeface — central configuration.
 *
 * DATABASE: two drivers supported, switch with the CODEFACE_DB_DRIVER
 * environment variable (or change the default below):
 *   - 'sqlite'  → zero-setup, file created + seeded automatically at database/data/codeface.sqlite
 *   - 'mysql'   → XAMPP default; the database `codeface` and all tables are
 *                 created automatically on first request (root / no password).
 *
 * No composer, no external packages — PDO only.
 */
return [
    'app' => [
        'name'    => 'Codeface',
        'tagline' => 'A gym for coders',
    ],
    'db' => [
        'driver'      => getenv('CODEFACE_DB_DRIVER') ?: 'sqlite',   // 'sqlite' | 'mysql'
        'sqlite_path' => getenv('CODEFACE_SQLITE') ?: __DIR__ . '/../../database/data/codeface.sqlite',
        'mysql'       => [
            'host'    => getenv('CODEFACE_DB_HOST') ?: '127.0.0.1',
            'port'    => getenv('CODEFACE_DB_PORT') ?: '3306',
            'name'    => getenv('CODEFACE_DB_NAME') ?: 'codeface',
            'user'    => getenv('CODEFACE_DB_USER') ?: 'root',
            'pass'    => getenv('CODEFACE_DB_PASS') !== false ? getenv('CODEFACE_DB_PASS') : '',
            'charset' => 'utf8mb4',
        ],
    ],
    'smtp' => [   /* forgot-password OTP emails (Gmail: App Password → see docs/DEPLOYMENT.md) */
        'host'      => getenv('CODEFACE_SMTP_HOST') ?: 'smtp.gmail.com',
        'port'      => (int)(getenv('CODEFACE_SMTP_PORT') ?: 587),
        'secure'    => getenv('CODEFACE_SMTP_SECURE') ?: 'tls',   // tls (STARTTLS) | ssl (465) | none (local test)
        'user'      => getenv('CODEFACE_SMTP_USER') ?: '',        // your Gmail address — empty = offline outbox.log mode
        'pass'      => getenv('CODEFACE_SMTP_PASS') ?: '',        // 16-char Gmail App Password (NOT your login password)
        'from'      => getenv('CODEFACE_SMTP_FROM') ?: (getenv('CODEFACE_SMTP_USER') ?: 'no-reply@codeface.local'),
        'from_name' => 'Codeface',
    ],
    'sse' => [
        'enabled'     => true,
        'max_seconds' => 50,   // each SSE connection self-terminates after this; EventSource auto-reconnects
        'tick_ms'     => 600,  // server poll interval inside a stream
    ],
];

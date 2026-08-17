<?php
declare(strict_types=1);

/**
 * Minimal SMTP client — plain PHP sockets, zero Composer packages.
 *
 * Gmail setup (the common case):
 *   1. Turn on 2-Step Verification for the Google account.
 *   2. Create an App Password: myaccount.google.com/apppasswords → "Mail".
 *   3. Set env CODEFACE_SMTP_USER=you@gmail.com and CODEFACE_SMTP_PASS=<16-char app password>
 *      (or edit backend/config/config.php → 'smtp').
 *   Works from XAMPP, shared hosting (if outbound 587 is open) and Docker.
 *
 * Offline/dev fallback: when no SMTP user/pass is configured, mail is appended to
 * database/data/outbox.log instead, so demos and tests still show the OTP.
 */

function cf_smtp_config(): array {
    global $config;
    $s = $config['smtp'] ?? [];
    return [
        'host'      => (string)($s['host'] ?? 'smtp.gmail.com'),
        'port'      => (int)($s['port'] ?? 587),
        'secure'    => (string)($s['secure'] ?? 'tls'),   // tls (STARTTLS) · ssl (port 465) · none (local test)
        'user'      => (string)($s['user'] ?? ''),
        'pass'      => (string)($s['pass'] ?? ''),
        'from'      => (string)($s['from'] ?? ''),
        'from_name' => (string)($s['from_name'] ?? 'Codeface'),
    ];
}

/** Read one (possibly multi-line) SMTP reply → [code, text]. */
function smtp_read($fp): array {
    $code = 0; $text = '';
    while (($line = fgets($fp, 515)) !== false) {
        $text .= $line;
        if (preg_match('/^(\d{3})[ -]/', $line, $m)) {
            $code = (int)$m[1];
            if ($line[3] === ' ') break;      // "250 text" = last line; "250-text" = more coming
        }
    }
    return [$code, $text];
}

/** Send one command and assert the reply code is one of $expect. */
function smtp_cmd($fp, string $cmd, array $expect): void {
    fwrite($fp, $cmd . "\r\n");
    [$code, $text] = smtp_read($fp);
    if (!in_array($code, $expect, true)) {
        throw new RuntimeException('SMTP refused `' . strtok($cmd, ' ') . '` → ' . $code . ' ' . trim($text));
    }
}

/**
 * Send a plain-text email. Returns true on success (or dev-log fallback), false on failure.
 */
function cf_mail_send(string $to, string $subject, string $textBody): bool {
    $c = cf_smtp_config();

    if ($c['user'] === '' || $c['pass'] === '') {
        $log = dirname(__DIR__, 2) . '/database/data/outbox.log';
        @file_put_contents($log,
            "==== " . date('c') . " — SMTP NOT CONFIGURED (offline dev mode) ====\n"
          . "To: $to\nSubject: $subject\n\n$textBody\n\n", FILE_APPEND);
        return true;
    }

    $from = $c['from'] !== '' ? $c['from'] : $c['user'];
    try {
        $transport = ($c['secure'] === 'ssl' ? 'ssl://' : '') . $c['host'];
        $fp = @fsockopen($transport, $c['port'], $errno, $errstr, 10);
        if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr");
        stream_set_timeout($fp, 15);

        [$code] = smtp_read($fp);
        if ($code !== 220) throw new RuntimeException("SMTP greeting → $code");

        smtp_cmd($fp, 'EHLO codeface.local', [250]);
        if ($c['secure'] === 'tls') {
            smtp_cmd($fp, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }
            smtp_cmd($fp, 'EHLO codeface.local', [250]);   // must re-EHLO after TLS
        }
        smtp_cmd($fp, 'AUTH LOGIN', [334]);
        smtp_cmd($fp, base64_encode($c['user']), [334]);
        smtp_cmd($fp, base64_encode($c['pass']), [235]);
        smtp_cmd($fp, "MAIL FROM:<$from>", [250]);
        smtp_cmd($fp, "RCPT TO:<$to>", [250, 251]);
        smtp_cmd($fp, 'DATA', [354]);

        $headers = 'From: ' . $c['from_name'] . " <$from>\r\n"
                 . "To: <$to>\r\n"
                 . 'Subject: ' . $subject . "\r\n"
                 . "MIME-Version: 1.0\r\n"
                 . "Content-Type: text/plain; charset=UTF-8\r\n"
                 . "Content-Transfer-Encoding: 8bit";
        $body = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $textBody));
        $body = str_replace("\n", "\r\n", $body);          // SMTP wants CRLF lines
        smtp_cmd($fp, $headers . "\r\n\r\n" . $body . "\r\n.", [250]);
        smtp_cmd($fp, 'QUIT', [221]);
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        if (isset($fp) && is_resource($fp)) fclose($fp);
        error_log('Codeface mailer: ' . $e->getMessage());
        return false;
    }
}

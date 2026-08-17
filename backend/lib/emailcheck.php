<?php
declare(strict_types=1);

/**
 * Fast sanity verdict for an email address: RFC format · real mail-server (MX)
 * records for its domain · "did you mean gmail.com?" typo suggestions.
 *
 * mx === false → the domain has NO MX record: it cannot receive email, so the mailbox
 *                cannot exist (parked typo-domains are caught too — no A-record fallback).
 * mx === null  → DNS lookup unavailable (offline demo) — never block the user.
 */

/** Can this server resolve DNS at all? (gmail.com always has MX.) */
function cf_dns_available(): bool {
    static $ok = null;
    if ($ok === null) {
        $ok = @checkdnsrr('gmail.com', 'MX')
           || @checkdnsrr('google.com', 'MX')
           || @checkdnsrr('cloudflare.com', 'A');
    }
    return $ok;
}

function cf_email_check(string $email): array {
    $email = trim($email);
    $out = ['email' => $email, 'format' => false, 'domain' => '', 'mx' => null, 'suggestion' => null];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return $out;
    $out['format'] = true;

    $domain = strtolower(substr((string)(strrchr($email, '@') ?: ''), 1));
    $out['domain'] = $domain;

    static $knownProviders = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.co.in', 'outlook.com',
        'hotmail.com', 'live.com', 'icloud.com', 'me.com', 'proton.me',
        'protonmail.com', 'zoho.com', 'aol.com', 'rediffmail.com',
    ];
    static $typos = [
        'gmial.com' => 'gmail.com',   'gmal.com' => 'gmail.com',   'gamil.com' => 'gmail.com',
        'gmil.com' => 'gmail.com',    'gmail.co' => 'gmail.com',   'gmail.comm' => 'gmail.com',
        'gmail.cm' => 'gmail.com',    'gmail.con' => 'gmail.com',  'gmai.com' => 'gmail.com',
        'gimail.com' => 'gmail.com',  'gnail.com' => 'gmail.com',  'gails.com' => 'gmail.com',
        'yahho.com' => 'yahoo.com',   'yaho.comm' => 'yahoo.com',  'yahoo.co' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com', 'hotmal.com' => 'hotmail.com', 'hotmail.co' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outloo.com' => 'outlook.com', 'outlook.co' => 'outlook.com',
    ];
    if (isset($typos[$domain])) {
        $out['suggestion'] = $typos[$domain];
    } elseif ($domain !== '') {
        foreach ($knownProviders as $k) {
            if (levenshtein($domain, $k) === 1) { $out['suggestion'] = $k; break; }
        }
    }

    // Strict MX lookup. No A-record fallback on purpose: typo/squatter domains
    // (gmial.com etc.) DO have parked A-records but can never deliver your mail.
    if (cf_dns_available()) {
        $out['mx'] = @checkdnsrr($domain, 'MX');
    }
    return $out;
}

/** One sentence for server-side red-alerts, or null when the address is acceptable. */
function cf_email_reject_reason(string $email): ?string {
    $c = cf_email_check($email);
    if (!$c['format']) return 'That email address does not look valid.';
    if ($c['mx'] === false) {
        $msg = '“' . $c['domain'] . '” has no mail server — mail sent there can never arrive, so this address can’t exist.';
        if ($c['suggestion']) $msg = 'Did you mean ' . $c['suggestion'] . '? ' . $msg;
        return $msg;
    }
    return null;
}

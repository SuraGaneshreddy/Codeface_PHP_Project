<?php
/* Read-only email sanity check used live by the login/register forms.
 * No auth needed; rate-limited per session so it can't be used as a DNS scanner. */
require __DIR__ . '/../../lib/bootstrap.php';

$email = trim((string)($_POST['email'] ?? (read_json_body()['email'] ?? '')));

$now = time();
$hits = array_values(array_filter((array)($_SESSION['email_checks'] ?? []), fn ($t) => is_int($t) && $t > $now - 60));
if (count($hits) >= 20) json_error('Slow down — too many checks.', 429);
$hits[] = $now;
$_SESSION['email_checks'] = $hits;

if ($email === '') json_error('No email given.');

json_response(['ok' => true] + cf_email_check($email));

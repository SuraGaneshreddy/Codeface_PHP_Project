<?php
require __DIR__ . '/../backend/lib/bootstrap.php';

if (current_user()) redirect('problems.php');

/* ── Email-verified signup ──────────────────────────────────────────────────
 * When SMTP credentials are configured (CODEFACE_SMTP_USER/PASS or config.php),
 * creating an account requires proving the mailbox exists: we email a 6-digit
 * code and only create the account after the right code comes back. A Gmail
 * that doesn't exist can never produce the code, so it can never register.
 * Without SMTP (offline XAMPP demo): classic one-step signup + live MX checks. */

$smtp = cf_smtp_config();
$verifyRequired = $smtp['user'] !== '' && $smtp['pass'] !== '';

$error = '';
$old = ['username' => '', 'email' => ''];
$pending = $_SESSION['pending_reg'] ?? null;   // ['username','email','hash','otp_hash','expires','attempts','sent_ts']
if ($pending && ($pending['expires'] ?? 0) < time()) { unset($_SESSION['pending_reg']); $pending = null; }

function cf_send_signup_otp(array $p): bool {
    global $config;
    $app  = $config['app']['name'] ?? 'Codeface';
    $body = "Hi {$p['username']},\n\n"
          . "Welcome to $app! To finish creating your account, enter this verification code:\n\n"
          . "    {$p['otp']}\n\n"
          . "It expires in 10 minutes. If you didn't try to sign up, just ignore this email.\n\n"
          . "— the $app team";
    return cf_mail_send($p['email'], "$app verification code: {$p['otp']}", $body);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'start');

    if ($action === 'verify' && $pending) {
        /* ── STEP 2: prove the mailbox exists ── */
        $code = trim((string)($_POST['otp'] ?? ''));
        if ((int)$pending['attempts'] >= 5) {
            $error = 'Too many wrong codes — start over to get a fresh one.';
        } else {
            $_SESSION['pending_reg']['attempts']++;
            $pending['attempts']++;
            if (preg_match('/^\d{6}$/', $code) && password_verify($code, $pending['otp_hash'])) {
                try {
                    $uid = create_user_account($pending['username'], $pending['email'], $pending['hash']);
                    unset($_SESSION['pending_reg']);
                    session_regenerate_id(true);
                    $_SESSION['uid'] = $uid;
                    redirect('problems.php');
                } catch (Throwable $ex) {
                    unset($_SESSION['pending_reg']); $pending = null;
                    $error = $ex->getMessage();
                }
            } else {
                $error = 'That code doesn’t match the email we sent — check it and try again.';
            }
        }
    } elseif ($action === 'resend' && $pending) {
        if (($pending['sent_ts'] ?? 0) > time() - 60) {
            $error = 'Please give it a minute before asking for another code.';
        } else {
            $_SESSION['pending_reg']['otp']      = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['pending_reg']['otp_hash'] = password_hash($_SESSION['pending_reg']['otp'], PASSWORD_DEFAULT);
            $_SESSION['pending_reg']['expires']  = time() + 600;
            $_SESSION['pending_reg']['attempts'] = 0;
            $_SESSION['pending_reg']['sent_ts']  = time();
            $pending = $_SESSION['pending_reg'];
            if (!cf_send_signup_otp($pending)) $error = 'Couldn’t send the email — try again in a moment.';
        }
    } else {
        /* ── STEP 1: validate; then either create (offline mode) or park + email a code ── */
        unset($_SESSION['pending_reg']); $pending = null;
        $old['username'] = trim((string)($_POST['username'] ?? ''));
        $old['email']    = trim((string)($_POST['email'] ?? ''));
        $password        = (string)($_POST['password'] ?? '');
        $confirm         = (string)($_POST['confirm'] ?? '');

        if ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } elseif (($emailErr = cf_email_reject_reason($old['email'])) !== null) {
            $error = $emailErr;   // red alert: undeliverable domain / typo / bad format
        } elseif ($verifyRequired) {
            if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $old['username'])) {
                $error = 'Username must be 3–20 characters: letters, numbers, underscores only.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif (db_one('SELECT id FROM users WHERE username = ?', [$old['username']])) {
                $error = 'That username is taken.';
            } elseif (db_one('SELECT id FROM users WHERE email = ?', [$old['email']])) {
                $error = 'An account with that email already exists.';
            } else {
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['pending_reg'] = [
                    'username' => $old['username'],
                    'email'    => $old['email'],
                    'hash'     => password_hash($password, PASSWORD_DEFAULT),
                    'otp'      => $otp,
                    'otp_hash' => password_hash($otp, PASSWORD_DEFAULT),
                    'expires'  => time() + 600,
                    'attempts' => 0,
                    'sent_ts'  => time(),
                ];
                $pending = $_SESSION['pending_reg'];
                if (!cf_send_signup_otp($pending)) {
                    unset($_SESSION['pending_reg']); $pending = null;
                    $error = 'Couldn’t send the verification email — check the address and try again.';
                }
            }
        } else {
            try {
                $uid = register_user($old['username'], $old['email'], $password);
                session_regenerate_id(true);
                $_SESSION['uid'] = $uid;
                redirect('problems.php');
            } catch (Throwable $ex) {
                $error = $ex->getMessage();
            }
        }
    }
}

$page_title = 'Create account';
$active = '';
$page_scripts = ['assets/js/email-check.js'];
require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container auth-wrap">
  <?php if ($pending): ?>
    <!-- STEP 2 — verify the mailbox actually exists -->
    <form class="card form-card" method="post" action="register.php" novalidate>
      <h1>Verify your email 📬</h1>
      <p class="form-sub">We emailed a 6-digit verification code to <strong><?= e($pending['email']) ?></strong>.
        Enter it below to create your account.</p>
      <p class="form-sub muted">If that mailbox doesn’t really exist, no code will ever arrive — so no account
        gets created for it. Check Spam/Promotions if it’s slow.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="verify">
      <label class="field">
        <span>Verification code</span>
        <input class="input" type="text" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6"
               autocomplete="one-time-code" placeholder="6-digit code" required autofocus>
        <small class="field-hint">Valid for 10 minutes · up to 5 tries.</small>
      </label>
      <button class="btn btn-primary btn-lg btn-block" type="submit">Verify &amp; create account</button>
    </form>
    <form class="card form-card" method="post" action="register.php" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="resend">
      <p class="form-alt">Didn’t get it? <button class="btn btn-ghost btn-sm" type="submit">Resend the code</button> ·
        <a href="register.php">start over</a></p>
    </form>
  <?php else: ?>
    <!-- STEP 1 — account details -->
    <form class="card form-card" method="post" action="register.php" novalidate>
      <h1>Create your account</h1>
      <p class="form-sub">Free forever. Two minutes to your first green checkmark.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <label class="field">
        <span>Username</span>
        <input class="input" type="text" name="username" value="<?= e($old['username']) ?>" maxlength="20" pattern="[A-Za-z0-9_]{3,20}" autocomplete="username" required autofocus>
        <small class="field-hint">3–20 letters, numbers or underscores.</small>
      </label>
      <label class="field">
        <span>Email</span>
        <input class="input" type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" data-emailcheck required>
        <small class="email-warn" data-emailhint role="status"></small>
      </label>
      <label class="field">
        <span>Password</span>
        <input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required>
        <small class="field-hint">At least 8 characters.</small>
      </label>
      <label class="field">
        <span>Confirm password</span>
        <input class="input" type="password" name="confirm" minlength="8" autocomplete="new-password" required>
      </label>
      <button class="btn btn-primary btn-lg btn-block" type="submit">
        <?= $verifyRequired ? 'Send verification code' : 'Create account' ?>
      </button>
      <?php if ($verifyRequired): ?>
        <p class="form-sub muted" style="margin-top:8px">We’ll email a 6-digit code first — only a real,
          deliverable mailbox can finish signing up.</p>
      <?php endif; ?>
      <p class="form-alt">Already have an account? <a href="login.php">Log in</a></p>
    </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

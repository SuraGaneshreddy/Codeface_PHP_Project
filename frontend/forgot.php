<?php
require __DIR__ . '/../backend/lib/bootstrap.php';

if (current_user()) redirect('problems.php');

$error = '';
$sent  = false;
$email = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));

    // per-session throttle: max 6 requests per hour
    $reqs = array_values(array_filter(
        (array)($_SESSION['forgot_reqs'] ?? []),
        fn ($t) => is_int($t) && $t > time() - 3600
    ));
    $_SESSION['forgot_reqs'] = $reqs;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address on your account.';
    } elseif (count($reqs) >= 6) {
        $error = 'Too many attempts from here — take a break and try again in about an hour.';
    } else {
        cf_password_reset_request($email);
        $_SESSION['forgot_reqs'][] = time();
        $sent = true;   // same message whether or not the email exists
    }
}

$page_title = 'Forgot password';
$active = '';
require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container auth-wrap">
  <div class="card form-card">
    <?php if ($sent): ?>
      <h1>Check your inbox 📬</h1>
      <p class="form-sub">If an account exists for <strong><?= e($email) ?></strong>, we just emailed a
        6-digit verification code. It expires in <strong>10 minutes</strong>.</p>
      <p class="form-sub muted">Can't find it? Check Spam/Promotions, and give it a minute to arrive.</p>
      <a class="btn btn-primary btn-lg btn-block" href="reset.php?email=<?= urlencode($email) ?>">Enter the code</a>
      <p class="form-alt"><a href="forgot.php">Use a different email</a> · <a href="login.php">Back to log in</a></p>
    <?php else: ?>
      <h1>Forgot password</h1>
      <p class="form-sub">Enter the email on your account — we'll send you a 6-digit verification code.</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="forgot.php" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="field">
          <span>Email</span>
          <input class="input" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
          <small class="field-hint">The address you registered with (e.g. your Gmail).</small>
        </label>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Send verification code</button>
      </form>
      <p class="form-alt"><a href="login.php">← Back to log in</a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

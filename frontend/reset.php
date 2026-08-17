<?php
require __DIR__ . '/../backend/lib/bootstrap.php';

if (current_user()) redirect('problems.php');

$error    = '';
$email    = trim((string)($_GET['email'] ?? ($_POST['email'] ?? '')));
$otp      = trim((string)($_POST['otp'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            cf_password_reset_verify($email, $otp, $password);
            $_SESSION['flash_login'] = 'Password updated — log in with your new password.';
            redirect('login.php');
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}

$page_title = 'Reset password';
$active = '';
require __DIR__ . '/../backend/partials/head.php';
require __DIR__ . '/../backend/partials/header.php';
?>
<div class="container auth-wrap">
  <form class="card form-card" method="post" action="reset.php" novalidate>
    <h1>Reset password</h1>
    <p class="form-sub">Enter the 6-digit code we emailed you, then choose a new password.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label class="field">
      <span>Email</span>
      <input class="input" type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required <?= $email !== '' ? 'readonly' : 'autofocus' ?>>
    </label>
    <label class="field">
      <span>Verification code</span>
      <input class="input" type="text" name="otp" value="<?= e($otp) ?>" inputmode="numeric" pattern="\d{6}" maxlength="6"
             autocomplete="one-time-code" placeholder="6-digit code" required <?= $email !== '' ? 'autofocus' : '' ?>>
      <small class="field-hint">Valid for 10 minutes · up to 5 tries.</small>
    </label>
    <label class="field">
      <span>New password</span>
      <input class="input" type="password" name="password" minlength="8" autocomplete="new-password" required>
    </label>
    <label class="field">
      <span>Confirm new password</span>
      <input class="input" type="password" name="confirm" minlength="8" autocomplete="new-password" required>
    </label>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Reset password</button>
    <p class="form-alt"><a href="forgot.php">Request a new code</a> · <a href="login.php">Back to log in</a></p>
  </form>
</div>
<?php require __DIR__ . '/../backend/partials/footer.php'; ?>

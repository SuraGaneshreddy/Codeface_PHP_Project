<?php
require __DIR__ . '/lib/bootstrap.php';

if (current_user()) redirect('problems.php');

$error = '';
$old = ['username' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $old['username'] = trim((string)($_POST['username'] ?? ''));
    $old['email']    = trim((string)($_POST['email'] ?? ''));
    $password        = (string)($_POST['password'] ?? '');
    $confirm         = (string)($_POST['confirm'] ?? '');

    if ($password !== $confirm) {
        $error = 'Passwords do not match.';
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

$page_title = 'Create account';
$active = '';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container auth-wrap">
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
      <input class="input" type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
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
    <button class="btn btn-primary btn-lg btn-block" type="submit">Create account</button>
    <p class="form-alt">Already have an account? <a href="login.php">Log in</a></p>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

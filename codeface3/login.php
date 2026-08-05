<?php
require __DIR__ . '/lib/bootstrap.php';

if (current_user()) redirect('problems.php');

$error = '';
$identity = '';
$next = $_GET['next'] ?? ($_POST['next'] ?? 'problems.php');
if (!is_string($next) || !preg_match('~^[a-z0-9_\-]+\.php(\?[a-z0-9_\-=&%]*)?$~i', $next)) {
    $next = 'problems.php';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();
    $identity = trim((string)($_POST['identity'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // session-based brute-force softening
    $_SESSION['login_fails'] = (int)($_SESSION['login_fails'] ?? 0);
    if ($_SESSION['login_fails'] >= 5) sleep(1);

    if ($identity === '' || $password === '') {
        $error = 'Enter your username (or email) and password.';
    } elseif ($user = login_attempt($identity, $password)) {
        $_SESSION['login_fails'] = 0;
        redirect($next);
    } else {
        $_SESSION['login_fails']++;
        $error = 'No account matches those credentials. Try again?';
    }
}

$page_title = 'Log in';
$active = '';
require __DIR__ . '/partials/head.php';
require __DIR__ . '/partials/header.php';
?>
<div class="container auth-wrap">
  <form class="card form-card" method="post" action="login.php" novalidate>
    <h1>Welcome back</h1>
    <p class="form-sub">Log in to keep your streak, rooms, and rating.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <label class="field">
      <span>Username or email</span>
      <input class="input" type="text" name="identity" value="<?= e($identity) ?>" autocomplete="username" required autofocus>
    </label>
    <label class="field">
      <span>Password</span>
      <input class="input" type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-lg btn-block" type="submit">Log in</button>
    <p class="form-alt">New to Codeface? <a href="register.php">Create an account</a></p>
    <p class="demo-hint">Demo accounts: <code>alice</code>, <code>bob</code>, <code>carol</code> — password <code>password123</code></p>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>

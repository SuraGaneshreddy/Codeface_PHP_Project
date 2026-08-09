<?php
/**
 * Top navigation. Expects: $active (string key: home|problems|rooms|leaderboard|profile|'')
 */
$active = $active ?? '';
$navUser = current_user();
function nav_link(string $href, string $key, string $label): void {
    global $active;
    $cls = $active === $key ? ' class="active"' : '';
    echo '<a href="' . e($href) . '"' . $cls . '>' . e($label) . '</a>';
}
?>
<body>
<header class="topnav">
  <div class="container nav-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark">{}</span>
      <span class="brand-name">Code<span>face</span></span>
    </a>
    <nav class="nav-links" id="navLinks">
      <?php nav_link('problems.php', 'problems', 'Practice'); ?>
      <?php nav_link('learn.php', 'learn', 'Learn'); ?>
      <?php nav_link('labs.php', 'labs', 'Labs'); ?>
      <?php nav_link('refactor.php', 'refactor', 'Refactor'); ?>
      <?php nav_link('rooms.php', 'rooms', 'Rooms'); ?>
      <?php nav_link('leaderboard.php', 'leaderboard', 'Leaderboard'); ?>
    </nav>
    <div class="nav-user">
      <?php if ($navUser): ?>
        <a class="user-chip" href="profile.php?u=<?= urlencode($navUser['username']) ?>" title="Your profile">
          <?= cf_avatar_html($navUser, 'avatar') ?>
          <span class="user-chip-name"><?= e($navUser['username']) ?></span>
          <span class="user-chip-rating" title="Rating"><?= (int)$navUser['rating'] ?></span>
        </a>
        <a class="btn btn-ghost btn-sm" href="logout.php">Log out</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="login.php">Log in</a>
        <a class="btn btn-primary btn-sm" href="register.php">Get started</a>
      <?php endif; ?>
      <button class="nav-burger" id="navBurger" aria-label="Menu" aria-expanded="false">☰</button>
    </div>
  </div>
</header>
<main class="page">

<?php
/**
 * Shared "level locked" wall (403) for Learn pages.
 * Expects in scope: $lang (track id), $meta (track meta), $level (locked level slug),
 * $lv (level array from cf_learn_levels()), $me (current user array or null).
 * The calling page must already have sent the 403 status + head/header partials.
 */
$order    = cf_learn_level_order();
$i        = array_search($level, $order, true);
$prevSlug = ($i === false || $i === 0) ? 'beginner' : $order[$i - 1];
$prev     = cf_learn_levels()[$prevSlug];
$prevProg = cf_learn_level_progress($lang, $prevSlug, $me ? (int)$me['id'] : null);
?>
<div class="container">
  <nav class="crumbs">
    <a href="learn.php">← All languages</a>
    <span class="crumb-sep">/</span>
    <a href="learn-track.php?l=<?= urlencode($lang) ?>"><?= e($meta['name']) ?></a>
  </nav>

  <div class="card lock-wall">
    <div class="lock-emoji" aria-hidden="true">🔒</div>
    <h1><?= e($lv['icon']) ?> <?= e($lv['name']) ?> is locked</h1>
    <p class="lock-reason">
      Finish <strong>all <?= $prevProg['total'] ?> lessons</strong> of
      <?= e($prev['icon']) ?> <?= e($prev['name']) ?> in <?= e($meta['name']) ?> to unlock this level.
      <?php if ($me): ?>
        You are at <strong><?= $prevProg['done'] ?>/<?= $prevProg['total'] ?></strong> — keep going!
      <?php endif; ?>
    </p>
    <div class="lock-progress" aria-hidden="true">
      <div class="progress"><span style="width:<?= $prevProg['total'] > 0 ? round(100 * $prevProg['done'] / $prevProg['total']) : 0 ?>%"></span></div>
    </div>
    <div class="lock-actions">
      <?php if (!$me): ?>
        <a class="btn btn-primary" href="login.php?next=learn-track.php%3Fl%3D<?= urlencode($lang) ?>">Log in to track progress</a>
      <?php endif; ?>
      <a class="btn <?= $me ? 'btn-primary' : 'btn-outline' ?>" href="learn-level.php?l=<?= urlencode($lang) ?>&amp;level=<?= e($prevSlug) ?>"><?= e($prev['icon']) ?> Go to <?= e($prev['name']) ?></a>
      <a class="btn btn-outline" href="learn-track.php?l=<?= urlencode($lang) ?>">All <?= e($meta['name']) ?> levels</a>
    </div>
    <p class="muted lock-note">Levels unlock in order: 🌱 Beginner → 🌿 Intermediate → 🌳 Advance → 🏆 Pro. Your completion is saved to your account.</p>
  </div>
</div>

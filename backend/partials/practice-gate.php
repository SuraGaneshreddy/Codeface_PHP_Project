<?php
/**
 * Shared "solve 10 practice problems first" lock wall (403) for gated sections
 * (Pro Labs, Refactor Gym).
 *
 * Expects in scope:
 *   $gateIcon     emoji for the section   (e.g. '🧪', '🧹')
 *   $gateSection  section name            (e.g. 'Pro Labs')
 *   $gateTag      one-line "what waits inside"  (e.g. 'multi-file legacy repos')
 *   $gateSolved   int — caller's cf_solved_problems_count() result
 *   $me           current user array or null
 *   $gateBackHref / $gateBackLabel  optional back link (default: Home)
 *   $gateLoginNext  optional safe relative page for the login redirect (default: problems.php)
 *
 * The calling page must already have sent the 403 status + head/header partials.
 */
$gateSolved    = max(0, min((int)$gateSolved, cf_practice_gate()));
$gateLeft      = cf_practice_gate() - $gateSolved;
$backHref      = $gateBackHref ?? 'index.php';
$backLabel     = $gateBackLabel ?? 'Home';
$loginNext     = $gateLoginNext ?? 'problems.php';
$pct           = (int)round(100 * $gateSolved / cf_practice_gate());
?>
<div class="container">
  <div class="card lock-wall">
    <div class="lock-emoji" aria-hidden="true">🔒</div>
    <h1><?= e($gateIcon) ?> <?= e($gateSection) ?> unlocks at <?= cf_practice_gate() ?> solved problems</h1>
    <p class="lock-reason">
      Inside <?= e($gateSection) ?> you'll face <strong><?= e($gateTag) ?></strong> — that assumes
      you can already solve small problems comfortably. So first,
      <strong>complete <?= cf_practice_gate() ?> problems in the Practice section</strong> (any difficulty, any topic).
      <?php if ($me): ?>
        You have <strong><?= $gateSolved ?>/<?= cf_practice_gate() ?></strong> solved —
        just <strong><?= $gateLeft ?> more</strong> and this page opens automatically. 💪
      <?php else: ?>
        <strong>Log in</strong> and your practice solves start counting toward the <?= cf_practice_gate() ?> you need.
      <?php endif; ?>
    </p>
    <div class="lock-progress" aria-hidden="true">
      <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
    </div>
    <div class="lock-actions">
      <?php if (!$me): ?>
        <a class="btn btn-primary" href="login.php?next=<?= urlencode($loginNext) ?>">Log in to start counting</a>
      <?php endif; ?>
      <a class="btn <?= $me ? 'btn-primary' : 'btn-outline' ?>" href="problems.php">⚔️ Go solve <?= $me ? $gateLeft : 10 ?> practice problem<?= $me && $gateLeft === 1 ? '' : 's' ?></a>
      <a class="btn btn-ghost" href="<?= e($backHref) ?>">← <?= e($backLabel) ?></a>
    </div>
    <p class="muted lock-note">
      Why a gate? Multi-file ships after fundamentals — that's how real teams onboard, too.
      Solved problems count automatically (✓ marks in Practice), and finishing <em>everything</em> later
      also wakes up the 🤖 AI content generator.
    </p>
  </div>
</div>

<?php
/** Footer. Optional: $page_scripts (string[] of src paths), $inline_script (raw JS). */
$year = gmdate('Y');
?>
</main>
<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <span class="brand-mark sm">{}</span> <strong>Codeface</strong> — a gym for coders.
      Practice deliberately. Pair often.
    </div>
    <div class="footer-meta">
      Built with vanilla HTML/CSS/JS, PHP &amp; SQL · © <?= e($year) ?>
    </div>
  </div>
</footer>
<div class="toast-stack" id="toastStack" aria-live="polite"></div>
<script src="assets/js/util.js"></script>
<?php foreach (($page_scripts ?? []) as $src): ?>
<script src="<?= e($src) ?>"></script>
<?php endforeach; ?>
<?php if (!empty($inline_script)): ?>
<script><?= $inline_script ?></script>
<?php endif; ?>
<script>
(function () {
  var b = document.getElementById('navBurger');
  var l = document.getElementById('navLinks');
  if (b && l) b.addEventListener('click', function () {
    var open = l.classList.toggle('open');
    b.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
</body>
</html>

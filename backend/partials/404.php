<?php
/* Shared styled 404. Expects the caller to have set http_response_code(404). */
$page_title = $page_title ?? 'Not found';
$active = $active ?? '';
require __DIR__ . '/head.php';
require __DIR__ . '/header.php';
?>
<div class="container">
  <div class="card empty-state">
    <h1>Nothing lives here (404)</h1>
    <p>That page may have been removed, renamed — or it was written by the AI for somebody else. 😉</p>
    <p><a href="index.php">Take me home</a></p>
  </div>
</div>
<?php require __DIR__ . '/footer.php'; ?>

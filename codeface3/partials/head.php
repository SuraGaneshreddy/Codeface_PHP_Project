<?php
/**
 * <head> section. Expects: $page_title (string), optional $extra_head (raw HTML).
 */
$page_title = $page_title ?? 'Codeface';
$cfgName = $config['app']['name'] ?? 'Codeface';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> · <?= e($cfgName) ?></title>
<meta name="description" content="Codeface — deliberate coding practice with real people. Problems, live pair-programming rooms, hackathons.">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Crect width='100' height='100' rx='20' fill='%236366f1'/%3E%3Ctext x='50' y='68' font-size='52' font-family='monospace' font-weight='bold' fill='white' text-anchor='middle'%3E{}%3C/text%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/app.css">
<?= $extra_head ?? '' ?>
</head>

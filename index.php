<?php
/* Codeface entry point.
 *
 * The project is organized into folders (see README.md):
 *   frontend/  — every page you can open in the browser (+ CSS/JS assets)
 *   backend/   — PHP engine (lib/), view partials, JSON API, config
 *   database/  — SQL schemas + the auto-created SQLite file
 *   docs/      — documentation · tools/ — dev test harnesses
 *
 * Send visitors straight to the frontend home page.
 */
header('Location: frontend/index.php');
exit;

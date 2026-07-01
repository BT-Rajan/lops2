<?php
/**
 * modules.php used to host placeholder "coming soon" pages. Tasks,
 * Calendar, and Documents have all since been built out as real pages
 * (tasks.php, calendar.php, documents.php) — nothing is left to place
 * here, so this just sends anyone who still hits the old URL onward.
 */
require_once __DIR__ . '/config/bootstrap.php';
require_login($auth);

header('Location: ' . base_url('dashboard.php'));
exit;

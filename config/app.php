<?php
/**
 * lops2 — application configuration.
 * Edit this file to match your XAMPP setup.
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'lops2');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application
define('APP_NAME', 'LegalOps');
define('APP_VERSION', '2.0.0');
define('APP_TIMEZONE', 'Asia/Kolkata');

// Show the real exception message on the error page (local development only).
// Set to false before this ever faces real clients — uncaught errors are
// always logged to storage/logs/app.log either way.
define('APP_DEBUG', true);

// URL: path to the public/ front-controller folder — no trailing slash.
// The app is served from htdocs/lops2/public/, so the base path must include
// /public (e.g. http://localhost/lops2/public/ → APP_BASE = '/lops2/public').
// If you instead set up a VHost pointing DocumentRoot straight at public/,
// change this to '' (empty string).
define('APP_BASE', '/lops2/public');

// File uploads — stored in storage/, served only through StorageController
define('STORAGE_PATH', dirname(__DIR__) . '/storage/');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

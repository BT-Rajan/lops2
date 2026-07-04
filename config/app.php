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

// URL: folder name under htdocs — no trailing slash.
// e.g. http://localhost/lops2  → APP_BASE = '/lops2'
define('APP_BASE', '/lops2');

// File uploads — stored in storage/, served only through StorageController
define('STORAGE_PATH', dirname(__DIR__) . '/storage/');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024);
define('UPLOAD_ALLOWED_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

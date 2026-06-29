<?php
/**
 * LegalOps — environment config.
 * Edit these four values for your XAMPP setup, then import sql/legalops.sql.
 */

// --- Database -----------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'legalops');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- App ------------------------------------------------------------------
// Folder name this app lives in under htdocs, e.g. http://localhost/legalops
define('APP_BASE_PATH', '/legalops');
define('APP_NAME', 'LegalOps');

// --- Client document uploads -----------------------------------------------
define('UPLOAD_DIR', __DIR__ . '/../uploads/clients/');
define('UPLOAD_MAX_BYTES', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_EXT', ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx']);

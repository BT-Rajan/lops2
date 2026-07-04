<?php
/**
 * lops2 bootstrap — required once by public/index.php and by CLI scripts.
 * After this file runs:
 *   $pdo       PDO instance
 *   $auth      PHPAuth\Auth instance
 *   $phpauth_config  PHPAuth\Config instance
 * are available, plus every function in helpers.php.
 */

require_once __DIR__ . '/app.php';

date_default_timezone_set(APP_TIMEZONE);

require_once __DIR__ . '/../app/Core/Autoloader.php';
require_once __DIR__ . '/../app/Core/helpers.php';

use Lops2\Core\View;
View::init(__DIR__ . '/../resources/views');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(503);
    $msg = htmlspecialchars($e->getMessage());
    die(<<<HTML
<!doctype html><meta charset="utf-8">
<title>Database unavailable — LegalOps</title>
<style>body{font-family:Segoe UI,sans-serif;max-width:580px;margin:80px auto;padding:0 24px;color:#1b1b1f}
h2{margin-top:0}code{background:#f3f4f6;padding:2px 6px;border-radius:4px}
.err{color:#888;font-size:13px;margin-top:16px}</style>
<h2>LegalOps can't reach the database</h2>
<p>Check <code>config/app.php</code> and make sure MySQL is running and
<code>database/schema.sql</code> has been imported.</p>
<p class="err">{$msg}</p>
HTML);
}

$phpauth_config = new \PHPAuth\Config($pdo);
$auth = new \PHPAuth\Auth($pdo, $phpauth_config);

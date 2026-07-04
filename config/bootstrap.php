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

/**
 * Global safety net. Without this, any uncaught exception/fatal error
 * (missing file, bad query, a bug in a controller) prints PHP's raw
 * warning/stack-trace output straight to the browser — file paths and
 * all. Log it instead and show the same ⚖ error page everything else
 * uses. Set APP_DEBUG = true in config/app.php to also see the message
 * on-screen while developing.
 */
function lops2_log_throwable(string $message, string $file, int $line, string $trace = ''): void
{
    $line = sprintf("[%s] %s in %s:%d\n%s\n\n", date('Y-m-d H:i:s'), $message, $file, $line, $trace);
    @file_put_contents(STORAGE_PATH . 'logs/app.log', $line, FILE_APPEND | LOCK_EX);
}

set_exception_handler(function (\Throwable $e): void {
    lops2_log_throwable(get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    $message = (defined('APP_DEBUG') && APP_DEBUG)
        ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
        : "We've logged the problem — please try again, or contact your administrator if it continues.";
    render_error_page(500, $message);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
        lops2_log_throwable($err['message'], $err['file'], $err['line']);
        $message = (defined('APP_DEBUG') && APP_DEBUG)
            ? $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')'
            : "We've logged the problem — please try again, or contact your administrator if it continues.";
        render_error_page(500, $message);
    }
});

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

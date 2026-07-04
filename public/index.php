<?php
/**
 * lops2 — front controller.
 * The ONLY publicly accessible PHP file. All requests pass through here.
 *
 * Under XAMPP: install as htdocs/lops2/public/index.php
 * Visit:       http://localhost/lops2/public/
 *
 * Or configure an Apache VHost pointing DocumentRoot at htdocs/lops2/public/
 * and visit http://legalops.local (cleaner, no /public/ in URL).
 */

define('LOPS2_ROOT', dirname(__DIR__));

// Bootstrap (loads config, PDO, PHPAuth, helpers, View)
require LOPS2_ROOT . '/config/bootstrap.php';

// Router
require LOPS2_ROOT . '/config/routes.php';
use Lops2\Core\Router;
Router::dispatch();

<?php
/**
 * lops2 autoloader.
 * Maps Lops2\* → app/
 *     PHPAuth\* → libs/PHPAuth/
 */
spl_autoload_register(function (string $class): void {
    // Autoloader.php lives in app/Core/ — two levels up is the lops2 root.
    $root = dirname(__DIR__, 2);
    $maps = [
        'Lops2\\'   => $root . '/app/',
        'PHPAuth\\' => $root . '/libs/PHPAuth/',
    ];
    foreach ($maps as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel  = substr($class, strlen($prefix));
            $file = $base . str_replace('\\', '/', $rel) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

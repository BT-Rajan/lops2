<?php
/**
 * LegalOps — install (essentials only).
 *
 * Creates the database (if missing) and every table from schema.sql.
 * No demo user, no sample cases/clients/invoices — just the structure
 * LegalOps needs to run. Register your first account at /register
 * afterwards, or run seed_demo.php if you want realistic data to test
 * against instead.
 *
 * Usage:
 *   php database/install.php
 */

require_once __DIR__ . '/lib.php';
lops2_cli_guard();
require_once __DIR__ . '/../config/app.php';

echo "LegalOps — install\n";
echo str_repeat('-', 40) . "\n";

try {
    echo "Connecting to MySQL/MariaDB at " . DB_HOST . " ...\n";
    $server = lops2_pdo_server();

    echo "Creating database `" . DB_NAME . "` (if it doesn't already exist) ...\n";
    $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');

    $pdo = lops2_pdo();

    echo "Running schema.sql ...\n";
    $count = lops2_run_sql_file($pdo, __DIR__ . '/schema.sql');
    echo "  {$count} statements executed.\n";

    echo "\nDone. The database is set up with no data in it.\n";
    echo "Next steps:\n";
    echo "  - Visit /register to create your first (admin) account, or\n";
    echo "  - Run `php database/seed_demo.php` for a full demo dataset to test against.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nInstall failed: " . $e->getMessage() . "\n");
    exit(1);
}

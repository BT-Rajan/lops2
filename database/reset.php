<?php
/**
 * LegalOps — full reset.
 *
 * Drops the entire database, recreates it from schema.sql, then loads
 * the demo dataset from seed_demo.php. For when your dev database has
 * gotten messy from testing and you want a known-good state back.
 *
 * This is destructive. It asks for confirmation unless you pass --force.
 *
 * Usage:
 *   php database/reset.php            (asks "type RESET to confirm")
 *   php database/reset.php --force    (no prompt — for scripted use)
 */

require_once __DIR__ . '/lib.php';
lops2_cli_guard();
require_once __DIR__ . '/../config/app.php';

$force = in_array('--force', $argv ?? [], true);

echo "LegalOps — full reset\n";
echo str_repeat('-', 40) . "\n";
echo "This will DROP the `" . DB_NAME . "` database entirely — every table,\n";
echo "every row — and rebuild it from schema.sql + seed_demo.php.\n\n";

if (!$force) {
    echo 'Type RESET to confirm: ';
    $answer = trim(fgets(STDIN));
    if ($answer !== 'RESET') {
        echo "Cancelled — nothing was touched.\n";
        exit(0);
    }
    echo "\n";
}

try {
    echo "Dropping database `" . DB_NAME . "` ...\n";
    $server = lops2_pdo_server();
    $server->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
    $server->exec('CREATE DATABASE `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
} catch (Throwable $e) {
    fwrite(STDERR, "Reset failed while dropping/creating the database: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n--- Rebuilding schema ---\n";
$installExit = 0;
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/install.php'), $installExit);
if ($installExit !== 0) {
    fwrite(STDERR, "\ninstall.php failed — stopping before seeding.\n");
    exit(1);
}

echo "\n--- Loading demo data ---\n";
$seedExit = 0;
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/seed_demo.php'), $seedExit);
if ($seedExit !== 0) {
    fwrite(STDERR, "\nseed_demo.php failed.\n");
    exit(1);
}

echo "\n" . str_repeat('-', 40) . "\n";
echo "Reset complete — fresh schema + full demo dataset loaded.\n";

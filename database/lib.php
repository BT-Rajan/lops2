<?php
/**
 * Shared helpers for database/install.php, seed_demo.php, and reset.php.
 * Not part of the routed app — these are operator tools, run from a
 * terminal, never through the browser.
 */

/** These scripts touch the whole database — never let them run over HTTP. */
function lops2_cli_guard(): void
{
    if (PHP_SAPI !== 'cli') {
        http_response_code(403);
        header('Content-Type: text/plain');
        exit("This is a command-line tool, not a web page.\nRun it from a terminal instead, e.g.:\n  php " . basename($_SERVER['SCRIPT_NAME'] ?? 'this-script.php') . "\n");
    }
}

/** Connect to the MySQL server without selecting a database (for CREATE DATABASE). */
function lops2_pdo_server(): PDO
{
    require_once __DIR__ . '/../config/app.php';
    return new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

/** Connect to the configured LegalOps database. */
function lops2_pdo(): PDO
{
    require_once __DIR__ . '/../config/app.php';
    return new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

/**
 * Split a .sql file into individual statements and run each one.
 * Quote-aware (tracks ' and " string state, including \' / \" escapes
 * and doubled '' quotes) so it doesn't break on a semicolon that
 * happens to sit inside a text value — a naive explode(';') would.
 */
function lops2_run_sql_file(PDO $pdo, string $path): int
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Could not read {$path}");
    }

    $statements = [];
    $buffer = '';
    $inString = null; // null | "'" | '"'
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buffer .= $ch;

        if ($inString !== null) {
            if ($ch === '\\') { // escaped char — consume the next one raw
                if ($i + 1 < $len) { $buffer .= $sql[++$i]; }
                continue;
            }
            if ($ch === $inString) {
                // doubled quote ('' or "") inside a string is a literal quote, not the end
                if ($i + 1 < $len && $sql[$i + 1] === $inString) {
                    $buffer .= $sql[++$i];
                } else {
                    $inString = null;
                }
            }
            continue;
        }

        if ($ch === "'" || $ch === '"') {
            $inString = $ch;
        } elseif ($ch === ';') {
            $statements[] = trim(substr($buffer, 0, -1));
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    $count = 0;
    foreach ($statements as $stmt) {
        // Strip full-line comments so a stray "--" line isn't sent as its own empty statement.
        $lines = array_filter(explode("\n", $stmt), fn($l) => trim($l) !== '' && !str_starts_with(trim($l), '--'));
        $clean = trim(implode("\n", $lines));
        if ($clean === '') continue;
        $pdo->exec($clean);
        $count++;
    }
    return $count;
}

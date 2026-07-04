<?php
/**
 * lops2 — background calendar sync cron.
 * Runs two-way sync for every active connected Google/Microsoft account.
 *
 * Schedule every 10-15 minutes:
 *   php /path/to/lops2/cron/calendar_sync.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('LOPS2_ROOT', dirname(__DIR__));
require LOPS2_ROOT . '/config/bootstrap.php';
require LOPS2_ROOT . '/libs/calendar_sync.php';

echo "[" . date('Y-m-d H:i:s') . "] Calendar sync cron starting\n";

$accounts = $pdo->query("SELECT * FROM legalops_calendar_accounts WHERE is_active = 1")->fetchAll();

if (!$accounts) {
    echo "  No connected calendar accounts. Done.\n";
    exit(0);
}

foreach ($accounts as $account) {
    $label  = $account['provider'] . ' (uid ' . $account['uid'] . ')';
    $result = sync_account($pdo, $account);
    if ($result['ok']) {
        echo "  {$label}: pushed {$result['pushed']}, imported {$result['imported']}, updated {$result['updated']}.\n";
    } else {
        echo "  {$label}: error — " . ($result['error'] ?: $result['push_errors'] . ' push error(s)') . "\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";

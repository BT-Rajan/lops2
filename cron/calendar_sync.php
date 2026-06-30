<?php
/**
 * Background calendar sync cron — runs sync_account() for every active
 * connected Google/Microsoft calendar account, so two-way sync happens
 * on a schedule rather than only when a user clicks "Sync now" on the
 * Calendar page.
 *
 * Suggested: every 10-15 minutes via Windows Task Scheduler / cron:
 *   "C:\xampp\php\php.exe" "C:\xampp\htdocs\legalops\cron\calendar_sync.php"
 *
 * CLI-only, same reasoning as hearing_reminders.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for command-line / scheduled task use only.\n");
}

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../libs/calendar_sync.php';

echo "[" . date('Y-m-d H:i:s') . "] Calendar sync cron starting\n";

$stmt = $pdo->query("SELECT * FROM legalops_calendar_accounts WHERE is_active = 1");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$accounts) {
    echo "  No connected calendar accounts. Nothing to do.\n";
    exit(0);
}

foreach ($accounts as $account) {
    $label = $account['provider'] . ' (uid ' . $account['uid'] . ')';
    $result = sync_account($pdo, $account);

    if ($result['ok']) {
        echo "  {$label}: pushed {$result['pushed']}, imported {$result['imported']}, updated {$result['updated']}.\n";
    } else {
        $err = $result['error'] ?: ($result['push_errors'] . ' push error(s)');
        echo "  {$label}: sync had issues — {$err}\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Calendar sync cron done.\n";

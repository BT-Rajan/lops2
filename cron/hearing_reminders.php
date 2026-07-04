<?php
/**
 * lops2 — daily hearing-reminder cron.
 *
 * Scans open cases for hearings exactly N days from today and creates a
 * task if one doesn't already exist for that case+date.
 * N = hearing_reminder_offset_days in legalops_settings (1 = tomorrow, 2 = day after).
 *
 * Schedule once per day, e.g.:
 *   Windows Task Scheduler: "C:\xampp\php\php.exe" "C:\xampp\htdocs\lops2\cron\hearing_reminders.php"
 *   Linux/macOS cron:       0 20 * * * php /path/to/lops2/cron/hearing_reminders.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('LOPS2_ROOT', dirname(__DIR__));
require LOPS2_ROOT . '/config/bootstrap.php';

$offset     = max(0, (int)get_setting($pdo, 'hearing_reminder_offset_days', '1'));
$targetDate = date('Y-m-d', strtotime("+{$offset} day"));
$now        = date('Y-m-d H:i:s');

echo "[{$now}] Hearing reminder cron — looking for hearings on {$targetDate} (offset {$offset} day(s))\n";

$stmt = $pdo->prepare(
    "SELECT id, case_number, title, next_hearing_date, next_hearing_time, created_by
     FROM legalops_cases
     WHERE next_hearing_date = ? AND status != 'closed'"
);
$stmt->execute([$targetDate]);
$cases = $stmt->fetchAll();

if (!$cases) {
    echo "  No hearings on {$targetDate}. Done.\n";
    exit(0);
}

$created = 0;
$skipped = 0;

foreach ($cases as $case) {
    $exists = $pdo->prepare(
        "SELECT id FROM legalops_tasks WHERE case_id = ? AND due_on = ? AND source = 'hearing_cron'"
    );
    $exists->execute([$case['id'], $targetDate]);
    if ($exists->fetch()) {
        echo "  Skipping {$case['case_number']} — already exists.\n";
        $skipped++;
        continue;
    }

    $assignTo  = $case['created_by'] ?: null;
    $timeNote  = $case['next_hearing_time'] ? ' at ' . date('g:i A', strtotime($case['next_hearing_time'])) : '';
    $notes     = 'Hearing for ' . $case['case_number'] . $timeNote . '. Auto-created by hearing cron.';

    $pdo->prepare(
        "INSERT INTO legalops_tasks (case_id,title,notes,due_on,due_time,priority,status,assigned_to,created_by,source)
         VALUES (?,?,?,?,?,'high','pending',?,?,'hearing_cron')"
    )->execute([$case['id'], 'Court hearing — ' . $case['title'], $notes, $targetDate, $case['next_hearing_time'], $assignTo, $assignTo]);

    if ($assignTo) {
        log_activity($pdo, (int)$assignTo, 'hearing_reminder', 'Created hearing reminder for ' . $case['case_number'], ['case_id' => $case['id']]);
    }

    echo "  Created task for {$case['case_number']} — {$case['title']}\n";
    $created++;
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Created: {$created}, skipped: {$skipped}.\n";

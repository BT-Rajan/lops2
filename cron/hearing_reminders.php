<?php
/**
 * Daily hearing-reminder cron.
 *
 * Looks at every case's next_hearing_date and, for any hearing exactly
 * N days from today (N = hearing_reminder_offset_days in Settings,
 * default 1 = "tomorrow"; set it to 2 for "day after tomorrow"), creates
 * a task for that day if one doesn't already exist for that case+date.
 *
 * Run once a day, e.g. via XAMPP's Windows Task Scheduler or cron:
 *   "C:\xampp\php\php.exe" "C:\xampp\htdocs\legalops\cron\hearing_reminders.php"
 *   php /path/to/legalops/cron/hearing_reminders.php
 *
 * CLI-only: refuses to run over HTTP so it can't be triggered remotely
 * by just guessing the URL.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is for command-line / scheduled task use only.\n");
}

require_once __DIR__ . '/../config/bootstrap.php';

$offsetDays = max(0, (int)get_setting($pdo, 'hearing_reminder_offset_days', '1'));
$targetDate = date('Y-m-d', strtotime("+{$offsetDays} day"));

echo "[" . date('Y-m-d H:i:s') . "] Hearing reminder cron — looking for hearings on {$targetDate} (offset: {$offsetDays} day(s))\n";

$stmt = $pdo->prepare(
    "SELECT id, case_number, title, next_hearing_date, next_hearing_time, created_by
     FROM legalops_cases
     WHERE next_hearing_date = ? AND status != 'closed'"
);
$stmt->execute([$targetDate]);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cases) {
    echo "  No hearings found for {$targetDate}. Nothing to do.\n";
    exit(0);
}

$created = 0;
$skipped = 0;

foreach ($cases as $case) {
    // Don't double up if this case already has a hearing task for that date
    // (covers the cron running twice in a day, or a manual task already existing).
    $existsStmt = $pdo->prepare(
        "SELECT id FROM legalops_tasks WHERE case_id = ? AND due_on = ? AND source = 'hearing_cron'"
    );
    $existsStmt->execute([$case['id'], $targetDate]);
    if ($existsStmt->fetch()) {
        echo "  Skipping {$case['case_number']} — reminder task already exists.\n";
        $skipped++;
        continue;
    }

    $title = 'Court hearing — ' . $case['title'];
    $timeNote = $case['next_hearing_time'] ? ' at ' . date('g:i A', strtotime($case['next_hearing_time'])) : '';
    $notes = 'Hearing for ' . $case['case_number'] . $timeNote . '. Auto-created by the hearing reminder cron.';

    $assignTo = $case['created_by'] ?: null;

    $insertStmt = $pdo->prepare(
        "INSERT INTO legalops_tasks (case_id, title, notes, due_on, due_time, priority, status, assigned_to, created_by, source)
         VALUES (?, ?, ?, ?, ?, 'high', 'pending', ?, ?, 'hearing_cron')"
    );
    $insertStmt->execute([
        $case['id'], $title, $notes, $targetDate, $case['next_hearing_time'],
        $assignTo, $assignTo,
    ]);

    if ($assignTo) {
        log_activity($pdo, (int)$assignTo, 'hearing_reminder', 'Created hearing reminder task for ' . $case['case_number']);
    }

    echo "  Created reminder task for {$case['case_number']} — {$case['title']}\n";
    $created++;
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Created: {$created}, skipped (already existed): {$skipped}.\n";

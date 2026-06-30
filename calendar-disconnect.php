<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    header('Location: ' . base_url('calendar.php'));
    exit;
}

$provider = $_POST['provider'] ?? '';
if (in_array($provider, ['google', 'microsoft'], true)) {
    // Scoped to uid = current user — can never disconnect someone else's.
    $pdo->prepare('DELETE FROM legalops_calendar_accounts WHERE uid = ? AND provider = ?')->execute([$uid, $provider]);
    log_activity($pdo, $uid, 'calendar_disconnected', 'Disconnected ' . ucfirst($provider) . ' Calendar');
    flash('success', ucfirst($provider) . ' Calendar disconnected.');
}

header('Location: ' . base_url('calendar.php'));
exit;

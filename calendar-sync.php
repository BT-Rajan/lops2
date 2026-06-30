<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/calendar_sync.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];

// Accept both the POST from the "Sync now" button and the GET redirect
// straight after first connecting an account.
$provider = $_POST['provider'] ?? $_GET['provider'] ?? '';
$isInitial = isset($_GET['initial']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
    flash('error', 'Your session expired before submitting. Please try again.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

if (!in_array($provider, ['google', 'microsoft'], true)) {
    flash('error', 'Unknown calendar provider.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

// A user can only ever sync THEIR OWN connected account — the lookup is
// scoped to uid = current user, full stop.
$stmt = $pdo->prepare('SELECT * FROM legalops_calendar_accounts WHERE uid = ? AND provider = ? AND is_active = 1');
$stmt->execute([$uid, $provider]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    flash('error', ucfirst($provider) . ' isn\'t connected.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

$result = sync_account($pdo, $account);

if ($result['ok']) {
    $msg = ucfirst($provider) . ' synced — pushed ' . $result['pushed'] . ', imported ' . $result['imported'] . ', updated ' . $result['updated'] . '.';
    flash('success', $msg);
} else {
    $detail = $result['error'] ?: ($result['push_errors'] . ' task(s) failed to push');
    flash('error', ucfirst($provider) . ' sync ran into an issue: ' . $detail);
}

header('Location: ' . base_url('calendar.php'));
exit;

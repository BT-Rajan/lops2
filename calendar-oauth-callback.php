<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/calendar_sync.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];

$provider = $_SESSION['oauth_provider'] ?? '';
$expectedState = $_SESSION['oauth_state'] ?? '';
unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    flash('error', 'Calendar connection was cancelled or denied.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

if (!$provider || !$code || !$state || !hash_equals($expectedState, $state)) {
    flash('error', 'That calendar connection request looks invalid — please try connecting again.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

$redirectUri = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
    . $_SERVER['HTTP_HOST'] . base_url('calendar-oauth-callback.php')
);

$result = $provider === 'google'
    ? google_exchange_code($pdo, $code, $redirectUri)
    : microsoft_exchange_code($pdo, $code, $redirectUri);

if (!$result['ok'] || empty($result['data']['access_token'])) {
    flash('error', 'Could not complete the connection to ' . ucfirst($provider) . ': ' . ($result['error'] ?: 'unknown error'));
    header('Location: ' . base_url('calendar.php'));
    exit;
}

$accessToken = $result['data']['access_token'];
$refreshToken = $result['data']['refresh_token'] ?? null;
$expiresIn = (int)($result['data']['expires_in'] ?? 3600);
$expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

// A user can only ever connect their OWN account — uid comes from the
// authenticated session, never from anything in the OAuth response.
$stmt = $pdo->prepare(
    'INSERT INTO legalops_calendar_accounts (uid, provider, access_token, refresh_token, token_expires_at, is_active)
     VALUES (?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE access_token = VALUES(access_token),
       refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
       token_expires_at = VALUES(token_expires_at), is_active = 1'
);
$stmt->execute([$uid, $provider, $accessToken, $refreshToken, $expiresAt]);

log_activity($pdo, $uid, 'calendar_connected', 'Connected ' . ucfirst($provider) . ' Calendar');
flash('success', ucfirst($provider) . ' Calendar connected. Running first sync…');

header('Location: ' . base_url('calendar-sync.php?initial=1&provider=' . $provider));
exit;

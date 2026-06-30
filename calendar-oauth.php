<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/calendar_sync.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];

$provider = $_GET['provider'] ?? '';
if (!in_array($provider, ['google', 'microsoft'], true)) {
    flash('error', 'Unknown calendar provider.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

$configured = get_setting($pdo, $provider . '_client_id') !== '';
if (!$configured) {
    flash('error', ucfirst($provider) . ' isn\'t configured yet — add OAuth credentials in Firm settings first.');
    header('Location: ' . base_url('calendar.php'));
    exit;
}

// State ties the callback back to this user + provider and guards against
// CSRF on the OAuth redirect — a random token stored in session, checked
// on the way back in calendar-oauth-callback.php.
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_provider'] = $provider;

$redirectUri = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
    . $_SERVER['HTTP_HOST'] . base_url('calendar-oauth-callback.php')
);

$url = $provider === 'google'
    ? google_oauth_url($pdo, $redirectUri, $state)
    : microsoft_oauth_url($pdo, $redirectUri, $state);

header('Location: ' . $url);
exit;

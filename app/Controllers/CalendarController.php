<?php

namespace Lops2\Controllers;

class CalendarController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireLogin();
        $uid  = $this->uid();
        $adm  = $this->isAdmin();

        $team = $adm ? $this->pdo->query("SELECT id,full_name,email FROM phpauth_users ORDER BY full_name")->fetchAll()
                     : [['id' => $uid, 'full_name' => $user['full_name'] ?? $user['email']]];

        $viewUserId = $uid;
        if ($adm && isset($_GET['user']) && (int)$_GET['user'] > 0) {
            $viewUserId = (int)$_GET['user'];
        }

        $year  = (int)($_GET['y'] ?? date('Y'));
        $month = (int)($_GET['m'] ?? date('n'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd   = date('Y-m-t', strtotime($monthStart));

        $stmt = $this->pdo->prepare(
            "SELECT t.*,c.case_number,c.title AS case_title FROM legalops_tasks t
             LEFT JOIN legalops_cases c ON c.id=t.case_id
             WHERE t.assigned_to=? AND t.due_on BETWEEN ? AND ?
             ORDER BY t.due_time IS NULL,t.due_time"
        );
        $stmt->execute([$viewUserId, $monthStart, $monthEnd]);
        $monthTasks = $stmt->fetchAll();

        $byDay = [];
        foreach ($monthTasks as $t) { $byDay[$t['due_on']][] = $t; }

        $stmt = $this->pdo->prepare('SELECT * FROM legalops_calendar_accounts WHERE uid=?');
        $stmt->execute([$uid]);
        $accounts = [];
        foreach ($stmt->fetchAll() as $a) { $accounts[$a['provider']] = $a; }

        $this->view('calendar/index', [
            'pageTitle'      => 'Calendar',
            'activeNav'      => 'calendar',
            'team'           => $team,
            'viewUserId'     => $viewUserId,
            'year'           => $year,
            'month'          => $month,
            'monthStart'     => $monthStart,
            'byDay'          => $byDay,
            'accounts'       => $accounts,
            'googleOk'       => get_setting($this->pdo, 'google_client_id') !== '',
            'microsoftOk'    => get_setting($this->pdo, 'microsoft_client_id') !== '',
        ]);
    }

    public function sync(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('calendar'); }

        $provider = $_POST['provider'] ?? '';
        if (!in_array($provider, ['google', 'microsoft'], true)) { flash('error', 'Unknown provider.'); $this->redirect('calendar'); }

        require_once dirname(__DIR__, 2) . '/libs/calendar_sync.php';
        $stmt = $this->pdo->prepare('SELECT * FROM legalops_calendar_accounts WHERE uid=? AND provider=? AND is_active=1');
        $stmt->execute([$this->uid(), $provider]);
        $account = $stmt->fetch();
        if (!$account) { flash('error', ucfirst($provider) . ' not connected.'); $this->redirect('calendar'); }

        $result = sync_account($this->pdo, $account);
        flash($result['ok'] ? 'success' : 'error',
            $result['ok']
                ? ucfirst($provider) . " synced — pushed {$result['pushed']}, imported {$result['imported']}, updated {$result['updated']}."
                : ucfirst($provider) . " sync error: " . ($result['error'] ?? 'unknown'));
        $this->redirect('calendar');
    }

    public function disconnect(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('calendar'); }

        $provider = $_POST['provider'] ?? '';
        if (in_array($provider, ['google', 'microsoft'], true)) {
            $this->pdo->prepare('DELETE FROM legalops_calendar_accounts WHERE uid=? AND provider=?')->execute([$this->uid(), $provider]);
            log_activity($this->pdo, $this->uid(), 'calendar_disconnected', 'Disconnected ' . ucfirst($provider) . ' Calendar');
            flash('success', ucfirst($provider) . ' Calendar disconnected.');
        }
        $this->redirect('calendar');
    }

    public function oauthStart(): void
    {
        $this->requireLogin();
        $provider = $_GET['provider'] ?? '';
        if (!in_array($provider, ['google', 'microsoft'], true)) { flash('error', 'Unknown provider.'); $this->redirect('calendar'); }
        if (get_setting($this->pdo, $provider . '_client_id') === '') { flash('error', ucfirst($provider) . ' not configured.'); $this->redirect('calendar'); }

        require_once dirname(__DIR__, 2) . '/libs/calendar_sync.php';
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_provider'] = $provider;
        $redirectUri = $this->callbackUri();

        $url = $provider === 'google'
            ? google_oauth_url($this->pdo, $redirectUri, $state)
            : microsoft_oauth_url($this->pdo, $redirectUri, $state);
        redirect_away($url);
    }

    public function oauthCallback(): void
    {
        $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/calendar_sync.php';

        $provider = $_SESSION['oauth_provider'] ?? '';
        $expected = $_SESSION['oauth_state'] ?? '';
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider']);

        if ($_GET['error'] ?? '') { flash('error', 'Connection was cancelled.'); $this->redirect('calendar'); }
        if (!$provider || !($_GET['code'] ?? '') || !hash_equals($expected, $_GET['state'] ?? '')) {
            flash('error', 'Invalid OAuth response — please try connecting again.'); $this->redirect('calendar');
        }

        $result = $provider === 'google'
            ? google_exchange_code($this->pdo, $_GET['code'], $this->callbackUri())
            : microsoft_exchange_code($this->pdo, $_GET['code'], $this->callbackUri());

        if (!$result['ok'] || empty($result['data']['access_token'])) {
            flash('error', 'Could not complete the connection: ' . ($result['error'] ?? 'unknown error')); $this->redirect('calendar');
        }

        $expiresAt = date('Y-m-d H:i:s', time() + (int)($result['data']['expires_in'] ?? 3600));
        $this->pdo->prepare('INSERT INTO legalops_calendar_accounts (uid,provider,access_token,refresh_token,token_expires_at,is_active) VALUES (?,?,?,?,?,1) ON DUPLICATE KEY UPDATE access_token=VALUES(access_token),refresh_token=COALESCE(VALUES(refresh_token),refresh_token),token_expires_at=VALUES(token_expires_at),is_active=1')
            ->execute([$this->uid(), $provider, $result['data']['access_token'], $result['data']['refresh_token'] ?? null, $expiresAt]);

        log_activity($this->pdo, $this->uid(), 'calendar_connected', 'Connected ' . ucfirst($provider) . ' Calendar');
        flash('success', ucfirst($provider) . ' Calendar connected. Running first sync…');

        // Trigger first sync via POST redirect
        $_POST['provider'] = $provider;
        $_POST['_token']   = csrf_token();
        $this->sync();
    }

    private function callbackUri(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'] . url('calendar/callback');
    }
}

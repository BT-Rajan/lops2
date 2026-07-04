<?php

namespace Lops2\Controllers;

class SettingsController extends BaseController
{
    public function index(): void
    {
        $this->requireAdmin();
        require_once dirname(__DIR__, 2) . '/libs/calendar_sync.php';

        $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $redirectUri = $scheme . '://' . $_SERVER['HTTP_HOST'] . url('calendar/callback');

        $this->view('settings/index', [
            'pageTitle'        => 'Firm settings',
            'activeNav'        => 'settings',
            'offset'           => (int)get_setting($this->pdo, 'hearing_reminder_offset_days', '1'),
            'googleClientId'   => get_setting($this->pdo, 'google_client_id'),
            'googleSecretSet'  => get_setting($this->pdo, 'google_client_secret') !== '',
            'msClientId'       => get_setting($this->pdo, 'microsoft_client_id'),
            'msSecretSet'      => get_setting($this->pdo, 'microsoft_client_secret') !== '',
            'redirectUri'      => $redirectUri,
            'team'             => $this->pdo->query("SELECT id,email,full_name,role FROM phpauth_users ORDER BY full_name")->fetchAll(),
        ]);
    }

    public function update(): void
    {
        $user = $this->requireAdmin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('settings'); }

        $form = $_POST['_form'] ?? '';

        if ($form === 'hearing') {
            $v = in_array((int)($_POST['hearing_reminder_offset_days'] ?? 1), [1, 2], true)
                ? (int)$_POST['hearing_reminder_offset_days'] : 1;
            set_setting($this->pdo, 'hearing_reminder_offset_days', (string)$v);
            flash('success', 'Hearing reminder timing saved.');

        } elseif ($form === 'google') {
            set_setting($this->pdo, 'google_client_id', trim($_POST['google_client_id'] ?? ''));
            if (($s = trim($_POST['google_client_secret'] ?? '')) !== '') set_setting($this->pdo, 'google_client_secret', $s);
            flash('success', 'Google credentials saved.');

        } elseif ($form === 'microsoft') {
            set_setting($this->pdo, 'microsoft_client_id', trim($_POST['microsoft_client_id'] ?? ''));
            if (($s = trim($_POST['microsoft_client_secret'] ?? '')) !== '') set_setting($this->pdo, 'microsoft_client_secret', $s);
            flash('success', 'Microsoft credentials saved.');

        } elseif ($form === 'role') {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $newRole  = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'member';
            if ($targetId === (int)$user['uid'] && $newRole === 'member') {
                flash('error', "You can't demote yourself — ask another admin.");
            } else {
                $this->pdo->prepare('UPDATE phpauth_users SET role=? WHERE id=?')->execute([$newRole, $targetId]);
                flash('success', 'Role updated.');
            }
        }

        $this->redirect('settings');
    }
}

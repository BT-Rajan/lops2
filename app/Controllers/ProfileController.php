<?php

namespace Lops2\Controllers;

class ProfileController extends BaseController
{
    public function index(): void
    {
        $user = $this->requireLogin();
        // Re-fetch in case it just changed
        $fresh = $this->auth->getUser((int)$user['uid']);
        $this->view('profile/index', [
            'pageTitle'  => 'My account',
            'activeNav'  => 'profile',
            'profileUser' => $fresh,
        ]);
    }

    public function update(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('profile'); }

        $form = $_POST['_form'] ?? '';
        $uid  = (int)$user['uid'];

        if ($form === 'profile') {
            $name = trim($_POST['full_name'] ?? '');
            if (!$name) { flash('error', 'Name required.'); $this->redirect('profile'); }
            $color = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['avatar_color'] ?? '') ? $_POST['avatar_color'] : '#3B6FE0';
            $this->auth->updateUser($uid, ['full_name' => $name, 'job_title' => trim($_POST['job_title'] ?? ''), 'avatar_color' => $color]);
            flash('success', 'Profile updated.');

        } elseif ($form === 'password') {
            $result = $this->auth->changePassword($uid, (string)($_POST['current_password'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['new_password_confirm'] ?? ''));
            flash($result['error'] ? 'error' : 'success', $result['message']);

        } elseif ($form === 'email') {
            $result = $this->auth->changeEmail($uid, trim($_POST['new_email'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
            flash($result['error'] ? 'error' : 'success', $result['message']);
        }

        $this->redirect('profile');
    }
}

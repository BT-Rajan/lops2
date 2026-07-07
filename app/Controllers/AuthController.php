<?php

namespace Lops2\Controllers;

class AuthController extends BaseController
{
    public function index(): void
    {
        redirect_if_logged_in($this->auth);
        $this->redirect('login');
    }

    public function loginForm(): void
    {
        redirect_if_logged_in($this->auth);
        $this->authView('auth/login', [
            'pageTitle'     => 'Sign in',
            'brandHeadline' => 'Practice management, run the way a good firm runs.',
            'brandSub'      => 'Cases, clients, deadlines and billing — one quiet, well-kept ledger.',
            'error'         => '',
            'email'         => '',
        ]);
    }

    public function login(): void
    {
        redirect_if_logged_in($this->auth);

        if (!csrf_valid()) {
            $this->authView('auth/login', ['pageTitle' => 'Sign in', 'error' => 'Session expired — please try again.', 'email' => '']);
            return;
        }

        $email    = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember']) ? 1 : 0;
        $result   = $this->auth->login($email, $password, $remember);

        if ($result['error']) {
            $this->authView('auth/login', ['pageTitle' => 'Sign in', 'error' => $result['message'], 'email' => $email]);
            return;
        }

        // Store session hash
        if ($this->phpauth_config->uses_session) {
            $_SESSION[$result['cookie_name']]                    = $result['hash'];
            $_SESSION[$result['cookie_name'] . '_expire']        = $result['expire'];
        } else {
            setcookie($result['cookie_name'], $result['hash'], $result['expire'],
                $this->phpauth_config->cookie_path, $this->phpauth_config->cookie_domain,
                (bool)$this->phpauth_config->cookie_secure, (bool)$this->phpauth_config->cookie_http);
        }

        $uid = $this->auth->getUID($email);
        log_activity($this->pdo, $uid, 'login', 'Signed in');
        flash('success', 'Welcome back.');
        $this->redirect('dashboard');
    }

    public function registerForm(): void
    {
        redirect_if_logged_in($this->auth);
        $this->authView('auth/register', [
            'pageTitle'     => 'Create account',
            'brandHeadline' => 'One workspace for every matter on your desk.',
            'brandSub'      => 'Set up your seat in under a minute — no mail server, no Composer, no waiting.',
            'error'         => '',
            'old'           => [],
        ]);
    }

    public function register(): void
    {
        redirect_if_logged_in($this->auth);

        $old = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
        ];

        if (!csrf_valid()) {
            $this->authView('auth/register', ['pageTitle' => 'Create account', 'error' => 'Session expired.', 'old' => $old]);
            return;
        }
        if ($old['full_name'] === '') {
            $this->authView('auth/register', ['pageTitle' => 'Create account', 'error' => 'Full name is required.', 'old' => $old]);
            return;
        }

        $result = $this->auth->register($old['email'], (string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''), [
            'full_name' => $old['full_name'],
            'job_title' => $old['job_title'] ?: 'Team member',
        ]);

        if ($result['error']) {
            $this->authView('auth/register', ['pageTitle' => 'Create account', 'error' => $result['message'], 'old' => $old]);
            return;
        }

        // The very first account on a fresh install becomes admin — otherwise
        // nobody could ever administer the firm, since only admins can change
        // roles and there'd be no admin yet to grant one. Mirrors the one-time
        // bootstrap in database/migrations/003_tasks_calendar_module.sql, but
        // at registration time so it also covers a fresh schema-only install
        // (no seed data) going through /register instead of a migration.
        $noAdminYet = (int)$this->pdo->query("SELECT COUNT(*) FROM phpauth_users WHERE role = 'admin'")->fetchColumn() === 0;
        if ($noAdminYet) {
            $this->pdo->prepare('UPDATE phpauth_users SET role = ? WHERE id = ?')->execute(['admin', (int)$result['uid']]);
        }

        log_activity($this->pdo, (int)$result['uid'], 'account_created', 'Created account');
        flash('success', 'Account created — sign in to continue.');
        $this->redirect('login');
    }

    public function forgotForm(): void
    {
        redirect_if_logged_in($this->auth);
        $this->authView('auth/forgot', ['pageTitle' => 'Forgot password', 'resetLink' => '', 'error' => '']);
    }

    public function forgot(): void
    {
        redirect_if_logged_in($this->auth);

        if (!csrf_valid()) {
            $this->authView('auth/forgot', ['pageTitle' => 'Forgot password', 'error' => 'Session expired.', 'resetLink' => '']);
            return;
        }

        $email  = trim($_POST['email'] ?? '');
        $result = $this->auth->requestReset($email, false);

        if ($result['error']) {
            $this->authView('auth/forgot', ['pageTitle' => 'Forgot password', 'error' => $result['message'], 'resetLink' => '']);
            return;
        }

        $resetLink = url('reset-password') . '?token=' . urlencode($result['token']);
        $this->authView('auth/forgot', ['pageTitle' => 'Forgot password', 'error' => '', 'resetLink' => $resetLink]);
    }

    public function resetForm(): void
    {
        redirect_if_logged_in($this->auth);
        $this->authView('auth/reset', ['pageTitle' => 'Set new password', 'token' => $_GET['token'] ?? '', 'error' => '', 'success' => false]);
    }

    public function reset(): void
    {
        redirect_if_logged_in($this->auth);
        $token  = trim($_POST['token'] ?? '');
        $result = $this->auth->resetPass($token, (string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));

        if ($result['error']) {
            $this->authView('auth/reset', ['pageTitle' => 'Set new password', 'token' => $token, 'error' => $result['message'], 'success' => false]);
            return;
        }
        $this->authView('auth/reset', ['pageTitle' => 'Set new password', 'token' => '', 'error' => '', 'success' => true]);
    }

    public function logout(): void
    {
        if ($this->auth->isLogged()) {
            $this->auth->logout($this->auth->getCurrentSessionHash());
        }
        session_destroy();
        $this->redirect('login');
    }
}

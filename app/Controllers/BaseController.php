<?php

namespace Lops2\Controllers;

use Lops2\Core\View;

abstract class BaseController
{
    protected \PDO $pdo;
    protected \PHPAuth\Auth $auth;
    protected \PHPAuth\Config $phpauth_config;
    protected ?array $user = null;

    public function __construct()
    {
        global $pdo, $auth, $phpauth_config;
        $this->pdo              = $pdo;
        $this->auth             = $auth;
        $this->phpauth_config   = $phpauth_config;
    }

    // ── Auth guards ───────────────────────────────────────────────────────────

    protected function requireLogin(): array
    {
        $this->user = require_login($this->auth);
        return $this->user;
    }

    protected function requireAdmin(): array
    {
        $this->user = require_admin($this->auth);
        return $this->user;
    }

    protected function uid(): int
    {
        return (int)($this->user['uid'] ?? 0);
    }

    protected function isAdmin(): bool
    {
        return is_admin($this->user);
    }

    // ── Responses ─────────────────────────────────────────────────────────────

    protected function view(string $template, array $data = [], string $layout = 'app'): void
    {
        View::render($template, array_merge($this->baseViewData(), $data), $layout);
    }

    protected function authView(string $template, array $data = []): void
    {
        View::render($template, $data, 'auth');
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        $base = APP_BASE;
        if ($ref && str_contains($ref, $base)) {
            header('Location: ' . $ref);
        } else {
            redirect('dashboard');
        }
        exit;
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $title = match ($status) {
            403 => '403 — Access denied',
            404 => '404 — Not found',
            default => $status . ' — Error',
        };
        $content = "<h1>{$title}</h1><p>" . htmlspecialchars($message) . "</p>";
        View::render('layouts/error', ['title' => $title, 'content' => $content], null);
        exit;
    }

    // ── Shared view data injected into every app layout ───────────────────────

    private function baseViewData(): array
    {
        $u = $this->user ?? [];
        // Per-user open-task count for the bell badge
        if (is_admin($u)) {
            $bellCount = (int)$this->pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done'")->fetchColumn();
        } else {
            $uid = (int)($u['uid'] ?? 0);
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done' AND (assigned_to=? OR created_by=?)");
            $stmt->execute([$uid, $uid]);
            $bellCount = (int)$stmt->fetchColumn();
        }
        return [
            'currentUser' => $u,
            'bellCount'   => $bellCount,
        ];
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    protected function validated(string $field, mixed $default = ''): mixed
    {
        return $_POST[$field] ?? $default;
    }

    protected function postBool(string $field): bool
    {
        return isset($_POST[$field]);
    }

    protected function postEnum(string $field, array $allowed, string $default): string
    {
        $v = $_POST[$field] ?? $default;
        return in_array($v, $allowed, true) ? $v : $default;
    }
}

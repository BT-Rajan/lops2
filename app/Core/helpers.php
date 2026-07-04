<?php
/**
 * lops2 — global helper functions.
 * Loaded once by bootstrap, available everywhere.
 */

// ── URL ──────────────────────────────────────────────────────────────────────

function url(string $path = ''): string
{
    return rtrim(APP_BASE, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function redirect_away(string $absolute): never
{
    header('Location: ' . $absolute);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $token = $_POST['_token'] ?? $_POST['csrf_token'] ?? '';   // accept both names
    return isset($_SESSION['csrf_token']) && $token !== ''
        && hash_equals($_SESSION['csrf_token'], $token);
}

// ── Flash ─────────────────────────────────────────────────────────────────────

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

// ── Auth guards ───────────────────────────────────────────────────────────────

function is_admin(?array $user): bool
{
    return ($user['role'] ?? 'member') === 'admin';
}

function require_login(\PHPAuth\Auth $auth): array
{
    if (!$auth->isLogged()) {
        redirect('login');
    }
    return $auth->getCurrentUser();
}

function redirect_if_logged_in(\PHPAuth\Auth $auth): void
{
    if ($auth->isLogged()) {
        redirect('dashboard');
    }
}

function require_admin(\PHPAuth\Auth $auth): array
{
    $user = require_login($auth);
    if (!is_admin($user)) {
        flash('error', "You'll need admin access for that.");
        redirect('dashboard');
    }
    return $user;
}

// ── Settings ──────────────────────────────────────────────────────────────────

function get_setting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM legalops_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $cache[$key] = ($value !== false ? $value : $default);
}

function set_setting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare('INSERT INTO legalops_settings (setting_key, setting_value) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE setting_value = ?')
        ->execute([$key, $value, $value]);
}

// ── Formatting ────────────────────────────────────────────────────────────────

function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/', $name);
    return count($parts) >= 2
        ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1))
        : mb_strtoupper(mb_substr($name, 0, 2));
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function fmt_date(?string $date, string $format = 'd M Y'): string
{
    return $date ? date($format, strtotime($date)) : '—';
}

function fmt_size(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── Activity log ──────────────────────────────────────────────────────────────

function log_activity(PDO $pdo, int $uid, string $action, string $description, array $ctx = []): void
{
    $pdo->prepare('INSERT INTO legalops_activity (uid, client_id, case_id, action, description)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$uid, $ctx['client_id'] ?? null, $ctx['case_id'] ?? null, $action, $description]);
}

// ── File uploads ──────────────────────────────────────────────────────────────

function _upload_file(array $file, string $destDir): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'Choose a file to upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed (error ' . $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'message' => 'File exceeds the 5 MB limit.'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        return ['ok' => false, 'message' => 'Disallowed file type — use PDF, JPG, PNG, DOC or DOCX.'];
    }
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return ['ok' => false, 'message' => 'Could not create storage folder.'];
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $destDir . '/' . $stored)) {
        return ['ok' => false, 'message' => 'Could not save the file.'];
    }
    return ['ok' => true, 'stored' => $stored, 'ext' => $ext];
}

function handle_client_upload(PDO $pdo, int $clientId, ?int $leadershipId, string $docType, array $file, int $uid): array
{
    $destDir = rtrim(STORAGE_PATH, '/') . '/uploads/clients/' . $clientId;
    $up = _upload_file($file, $destDir);
    if (!$up['ok']) return $up;

    $pdo->prepare('INSERT INTO legalops_client_documents
                   (client_id, leadership_id, doc_type, stored_name, original_name, mime_type, file_size, uploaded_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$clientId, $leadershipId, $docType, $up['stored'],
                   basename($file['name']), $file['type'] ?? null, $file['size'], $uid]);

    return ['ok' => true, 'message' => 'Document uploaded.', 'id' => (int)$pdo->lastInsertId()];
}

function handle_case_upload(PDO $pdo, int $caseId, string $docType, array $file, int $uid, ?string $notes = null): array
{
    $destDir = rtrim(STORAGE_PATH, '/') . '/uploads/cases/' . $caseId;
    $up = _upload_file($file, $destDir);
    if (!$up['ok']) return $up;

    $pdo->prepare('INSERT INTO legalops_case_documents
                   (case_id, doc_type, stored_name, original_name, mime_type, file_size, notes, uploaded_by)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$caseId, $docType, $up['stored'],
                   basename($file['name']), $file['type'] ?? null, $file['size'], $notes, $uid]);

    return ['ok' => true, 'message' => 'Document uploaded.', 'id' => (int)$pdo->lastInsertId()];
}

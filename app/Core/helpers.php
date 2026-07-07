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

/**
 * Same as url(), but for static assets (CSS/JS) — appends the file's
 * last-modified time as a cache-busting query string. Without this, a
 * fixed CSS/JS bug can silently fail to show up for someone whose
 * browser already cached the old file, since there's no build step
 * here that hashes filenames on change.
 */
function asset_url(string $path): string
{
    $full = LOPS2_ROOT . '/public/' . ltrim($path, '/');
    $version = is_file($full) ? filemtime($full) : time();
    return url($path) . '?v=' . $version;
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

// ── Error pages ───────────────────────────────────────────────────────────────

/**
 * Render the shared ⚖ error page with a status-appropriate title and the
 * given message, then stop. Used by Router::dispatch()'s 404 fallback,
 * BaseController::abort(), and the global exception handler below —
 * previously each of these built the page slightly differently, and the
 * Router's 404 branch forgot to set $content at all (it always fell back
 * to the layout's generic "Something went wrong" text, even for a plain
 * page-not-found).
 */
function render_error_page(int $status, string $message = ''): never
{
    if (!headers_sent()) {
        http_response_code($status);
    }
    $title = match ($status) {
        403 => '403 — Access denied',
        404 => '404 — Page not found',
        default => $status . ' — Something went wrong',
    };
    $content = '<h1>' . htmlspecialchars($title) . '</h1>'
        . ($message !== '' ? '<p>' . htmlspecialchars($message) . '</p>' : '');
    require LOPS2_ROOT . '/resources/views/layouts/error.php';
    exit;
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

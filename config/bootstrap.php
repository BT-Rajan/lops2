<?php
/**
 * LegalOps — bootstrap.
 * Included at the top of every page. Sets up the DB connection, PHPAuth,
 * the session, and a few small helpers used throughout the app.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../libs/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(
        '<div style="font-family:Segoe UI,sans-serif;max-width:640px;margin:80px auto;padding:32px;' .
        'border:1px solid #e3e3e3;border-radius:12px;background:#fff;color:#1b1b1f">' .
        '<h2 style="margin-top:0">LegalOps can\'t reach the database</h2>' .
        '<p>Check <code>config/config.php</code> for the DB name/credentials, and make sure ' .
        '<code>sql/legalops.sql</code> has been imported into MySQL.</p>' .
        '<p style="color:#888;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>'
    );
}

$phpauth_config = new \PHPAuth\Config($pdo);
$auth = new \PHPAuth\Auth($pdo, $phpauth_config);

/**
 * Was the request a POST with a valid CSRF token? Always check before
 * mutating anything.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

/** Stash a one-shot flash message for the next page load. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Pull (and clear) the pending flash message, if any. */
function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function base_url(string $path = ''): string
{
    return rtrim(APP_BASE_PATH, '/') . '/' . ltrim($path, '/');
}

/** Send anyone who isn't logged in back to the login screen. */
function require_login(\PHPAuth\Auth $auth): array
{
    if (!$auth->isLogged()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
    return $auth->getCurrentUser();
}

/** Send anyone who IS logged in straight to the dashboard. */
function redirect_if_logged_in(\PHPAuth\Auth $auth): void
{
    if ($auth->isLogged()) {
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

function log_activity(PDO $pdo, int $uid, string $action, string $description): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO legalops_activity (uid, action, description) VALUES (?, ?, ?)'
    );
    $stmt->execute([$uid, $action, $description]);
}

/** Two-letter initials for an avatar badge, from a full name or email. */
function initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $name);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
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

/**
 * Validate and store an uploaded client document. Files are saved under
 * UPLOAD_DIR/{client_id}/ with a random name — never the original
 * filename — and the folder is denied to direct web access via
 * uploads/.htaccess, so the only way to retrieve a file is through
 * download.php after an auth + ownership check.
 *
 * @return array{ok: bool, message: string, id?: int}
 */
function handle_client_upload(PDO $pdo, int $clientId, ?int $leadershipId, string $docType, array $file, int $uploadedBy): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'message' => 'Choose a file to upload.'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Upload failed (error code ' . $file['error'] . ').'];
    }
    if ($file['size'] > UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'message' => 'File is larger than the 5MB limit.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        return ['ok' => false, 'message' => 'That file type isn\'t allowed. Use PDF, JPG, PNG, DOC or DOCX.'];
    }

    $clientDir = rtrim(UPLOAD_DIR, '/') . '/' . $clientId;
    if (!is_dir($clientDir) && !mkdir($clientDir, 0755, true) && !is_dir($clientDir)) {
        return ['ok' => false, 'message' => 'Could not create the upload folder on the server.'];
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $clientDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['ok' => false, 'message' => 'Could not save the uploaded file.'];
    }

    $stmt = $pdo->prepare(
        'INSERT INTO legalops_client_documents
         (client_id, leadership_id, doc_type, stored_name, original_name, mime_type, file_size, uploaded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $clientId, $leadershipId, $docType, $storedName,
        basename($file['name']), $file['type'] ?? null, $file['size'], $uploadedBy,
    ]);

    return ['ok' => true, 'message' => 'Document uploaded.', 'id' => (int)$pdo->lastInsertId()];
}

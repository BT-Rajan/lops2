<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_admin($auth);
$uid = (int)$current_user['uid'];

$page_title = 'Firm settings';
$active_nav = 'settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'hearing') {
        $offset = (int)($_POST['hearing_reminder_offset_days'] ?? 1);
        $offset = in_array($offset, [1, 2], true) ? $offset : 1;
        set_setting($pdo, 'hearing_reminder_offset_days', (string)$offset);
        flash('success', 'Hearing reminder timing updated.');
    } elseif ($form === 'google') {
        set_setting($pdo, 'google_client_id', trim($_POST['google_client_id'] ?? ''));
        if (trim($_POST['google_client_secret'] ?? '') !== '') {
            set_setting($pdo, 'google_client_secret', trim($_POST['google_client_secret']));
        }
        flash('success', 'Google Calendar credentials saved.');
    } elseif ($form === 'microsoft') {
        set_setting($pdo, 'microsoft_client_id', trim($_POST['microsoft_client_id'] ?? ''));
        if (trim($_POST['microsoft_client_secret'] ?? '') !== '') {
            set_setting($pdo, 'microsoft_client_secret', trim($_POST['microsoft_client_secret']));
        }
        flash('success', 'Microsoft Calendar credentials saved.');
    } elseif ($form === 'role') {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $newRole = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'member';

        if ($targetId === $uid && $newRole === 'member') {
            flash('error', "You can't demote yourself — ask another admin to do it.");
        } else {
            $pdo->prepare('UPDATE phpauth_users SET role = ? WHERE id = ?')->execute([$newRole, $targetId]);
            flash('success', 'Role updated.');
        }
    }

    header('Location: ' . base_url('settings.php'));
    exit;
}

$offset = (int)get_setting($pdo, 'hearing_reminder_offset_days', '1');
$googleClientId = get_setting($pdo, 'google_client_id');
$googleClientSecretSet = get_setting($pdo, 'google_client_secret') !== '';
$msClientId = get_setting($pdo, 'microsoft_client_id');
$msClientSecretSet = get_setting($pdo, 'microsoft_client_secret') !== '';

$team = $pdo->query("SELECT id, email, full_name, role, isactive FROM phpauth_users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);

$redirectUri = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
    . $_SERVER['HTTP_HOST'] . base_url('calendar-oauth-callback.php')
);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Admin</span>
    <h1>Firm settings</h1>
    <p class="sub">Hearing reminders, calendar integrations, and team access.</p>
  </div>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Hearing reminder timing -->
    <div class="card card-pad">
      <div class="card-head"><h3>Court hearing reminders</h3></div>
      <p class="case-client" style="margin-bottom:14px">Each night, a scheduled task scans every open matter's next hearing date and creates a reminder task automatically. Choose how far ahead it should fire.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="hearing">
        <div class="input-row" style="margin-bottom:16px">
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;flex:1;border:1.5px solid var(--line);border-radius:8px;padding:11px 14px;cursor:pointer">
            <input type="radio" name="hearing_reminder_offset_days" value="1" <?= $offset === 1 ? 'checked' : '' ?>>
            Tomorrow <span style="color:var(--text-muted);font-weight:400">(1 day before)</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;flex:1;border:1.5px solid var(--line);border-radius:8px;padding:11px 14px;cursor:pointer">
            <input type="radio" name="hearing_reminder_offset_days" value="2" <?= $offset === 2 ? 'checked' : '' ?>>
            Day after tomorrow <span style="color:var(--text-muted);font-weight:400">(2 days before)</span>
          </label>
        </div>
        <button class="btn btn-primary" type="submit">Save timing</button>
      </form>
      <div class="alert alert-info" style="margin-top:16px;margin-bottom:0">
        Schedule <code>cron/hearing_reminders.php</code> to run once daily (e.g. via Windows Task Scheduler running
        <code>php.exe cron\hearing_reminders.php</code>) and <code>cron/calendar_sync.php</code> every 10–15 minutes for background two-way sync.
      </div>
    </div>

    <!-- Team & roles -->
    <div class="card card-pad">
      <div class="card-head"><h3>Team &amp; roles</h3></div>
      <p class="case-client" style="margin-bottom:14px">Admins see and manage every task and calendar across the firm. Members only ever see their own.</p>
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
        <tbody>
          <?php foreach ($team as $t): ?>
          <tr>
            <td class="case-title"><?= htmlspecialchars($t['full_name'] ?: '—') ?></td>
            <td class="case-client"><?= htmlspecialchars($t['email']) ?></td>
            <td>
              <form method="post" style="display:flex;align-items:center;gap:8px">
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="role">
                <input type="hidden" name="user_id" value="<?= (int)$t['id'] ?>">
                <select class="input" name="role" style="padding:6px 10px;font-size:12.5px" onchange="this.form.submit()" <?= (int)$t['id'] === $uid ? 'disabled title="Use another admin account to change your own role"' : '' ?>>
                  <option value="member" <?= $t['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                  <option value="admin" <?= $t['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Google OAuth -->
    <div class="card card-pad">
      <div class="card-head"><h3>Google Calendar integration</h3></div>
      <p class="case-client" style="margin-bottom:10px">Create an OAuth 2.0 client (type: Web application) in Google Cloud Console, add the redirect URI below, then paste the credentials here.</p>
      <div class="reset-token-box" style="margin-bottom:14px">
        <strong>Authorized redirect URI:</strong><br><span class="mono"><?= htmlspecialchars($redirectUri) ?></span>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="google">
        <div class="field"><label>Client ID</label><input class="input mono" type="text" name="google_client_id" value="<?= htmlspecialchars($googleClientId) ?>"></div>
        <div class="field"><label>Client secret</label><input class="input mono" type="password" name="google_client_secret" placeholder="<?= $googleClientSecretSet ? '••••••••  (already set — leave blank to keep)' : 'Not set' ?>"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save Google credentials</button>
      </form>
    </div>

    <!-- Microsoft OAuth -->
    <div class="card card-pad">
      <div class="card-head"><h3>Microsoft Calendar integration</h3></div>
      <p class="case-client" style="margin-bottom:10px">Register an app in Azure AD / Entra admin center, add the redirect URI below as a Web platform redirect, then paste the credentials here.</p>
      <div class="reset-token-box" style="margin-bottom:14px">
        <strong>Redirect URI:</strong><br><span class="mono"><?= htmlspecialchars($redirectUri) ?></span>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="microsoft">
        <div class="field"><label>Application (client) ID</label><input class="input mono" type="text" name="microsoft_client_id" value="<?= htmlspecialchars($msClientId) ?>"></div>
        <div class="field"><label>Client secret</label><input class="input mono" type="password" name="microsoft_client_secret" placeholder="<?= $msClientSecretSet ? '••••••••  (already set — leave blank to keep)' : 'Not set' ?>"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save Microsoft credentials</button>
      </form>
    </div>

  </div>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

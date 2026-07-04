<?php
// settings/index.php
?>
<div class="page-head">
  <div><span class="eyebrow-gold">Admin</span><h1>Firm settings</h1><p class="sub">Hearing reminders, calendar integrations, and team access.</p></div>
</div>
<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card card-pad">
      <div class="card-head"><h3>Court hearing reminders</h3></div>
      <p class="case-client" style="margin-bottom:14px">How far ahead should the nightly cron create a reminder task before a hearing?</p>
      <form method="post" action="<?= url('settings') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="hearing">
        <div class="input-row" style="margin-bottom:16px">
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;flex:1;border:1.5px solid var(--line);border-radius:8px;padding:11px 14px;cursor:pointer">
            <input type="radio" name="hearing_reminder_offset_days" value="1" <?= $offset===1?'checked':'' ?>> Tomorrow <span style="color:var(--text-muted);font-weight:400">(1 day before)</span>
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-weight:500;flex:1;border:1.5px solid var(--line);border-radius:8px;padding:11px 14px;cursor:pointer">
            <input type="radio" name="hearing_reminder_offset_days" value="2" <?= $offset===2?'checked':'' ?>> Day after tomorrow <span style="color:var(--text-muted);font-weight:400">(2 days before)</span>
          </label>
        </div>
        <button class="btn btn-primary" type="submit">Save</button>
      </form>
      <div class="alert alert-info" style="margin-top:14px;margin-bottom:0">
        Schedule <code>cron/hearing_reminders.php</code> daily and <code>cron/calendar_sync.php</code> every 10–15 min.
      </div>
    </div>

    <div class="card card-pad">
      <div class="card-head"><h3>Team &amp; roles</h3></div>
      <p class="case-client" style="margin-bottom:14px">Admins see all tasks and calendars firm-wide. Members only see their own.</p>
      <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
        <tbody>
          <?php foreach ($team as $t): ?>
          <tr>
            <td class="case-title"><?= htmlspecialchars($t['full_name'] ?: '—') ?></td>
            <td class="case-client"><?= htmlspecialchars($t['email']) ?></td>
            <td>
              <form method="post" action="<?= url('settings') ?>">
                <?= csrf_field() ?><input type="hidden" name="_form" value="role"><input type="hidden" name="user_id" value="<?= $t['id'] ?>">
                <select class="input" name="role" style="padding:6px 10px;font-size:12.5px" onchange="this.form.submit()" <?= (int)$t['id']===(int)$currentUser['uid']?'disabled':'' ?>>
                  <option value="member" <?= $t['role']==='member'?'selected':'' ?>>Member</option>
                  <option value="admin" <?= $t['role']==='admin'?'selected':'' ?>>Admin</option>
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
    <?php foreach (['google' => 'Google Calendar', 'microsoft' => 'Microsoft Outlook Calendar'] as $prov => $lbl): ?>
    <div class="card card-pad">
      <div class="card-head"><h3><?= $lbl ?> integration</h3></div>
      <div class="reset-token-box" style="margin-bottom:14px">
        <strong>Redirect URI:</strong><br><span class="mono" style="font-size:12px"><?= htmlspecialchars($redirectUri) ?></span>
      </div>
      <form method="post" action="<?= url('settings') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="<?= $prov ?>">
        <div class="field"><label>Client ID</label><input class="input mono" type="text" name="<?= $prov ?>_client_id" value="<?= htmlspecialchars($prov==='google' ? $googleClientId : $msClientId) ?>"></div>
        <div class="field"><label>Client secret</label><input class="input mono" type="password" name="<?= $prov ?>_client_secret" placeholder="<?= ($prov==='google'?$googleSecretSet:$msSecretSet) ? '•••• (already set)' : 'Not set' ?>"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save credentials</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>

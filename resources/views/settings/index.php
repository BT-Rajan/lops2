<?php
// settings/index.php
?>
<div class="page-head">
  <div><span class="eyebrow-gold">Admin</span><h1>Firm settings</h1><p class="sub">Calendar integrations, hearing reminders, and team access.</p></div>
</div>

<div style="display:flex;flex-direction:column;gap:20px">

  <!-- Calendar sync -->
  <div class="card card-pad">
    <div class="card-head"><h3>Calendar sync</h3></div>
    <div class="reset-token-box" style="margin-bottom:16px">
      <strong>Redirect URI</strong> <span class="small muted">(same for both providers — add it in each one's OAuth app settings)</span><br>
      <span class="mono" style="font-size:12px;word-break:break-all"><?= htmlspecialchars($redirectUri) ?></span>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:18px" role="tablist">
      <button type="button" class="filter-chip active" data-settings-tab="google" role="tab" aria-selected="true">Google Calendar</button>
      <button type="button" class="filter-chip" data-settings-tab="microsoft" role="tab" aria-selected="false">Microsoft Outlook</button>
    </div>

    <div data-settings-panel="google">
      <form method="post" action="<?= url('settings') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="google">
        <div class="field"><label>Client ID</label><input class="input mono" type="text" name="google_client_id" value="<?= htmlspecialchars($googleClientId) ?>"></div>
        <div class="field"><label>Client secret</label><input class="input mono" type="password" name="google_client_secret" placeholder="<?= $googleSecretSet ? '•••• (already set)' : 'Not set' ?>"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save Google credentials</button>
      </form>
    </div>
    <div data-settings-panel="microsoft" style="display:none">
      <form method="post" action="<?= url('settings') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="microsoft">
        <div class="field"><label>Client ID</label><input class="input mono" type="text" name="microsoft_client_id" value="<?= htmlspecialchars($msClientId) ?>"></div>
        <div class="field"><label>Client secret</label><input class="input mono" type="password" name="microsoft_client_secret" placeholder="<?= $msSecretSet ? '•••• (already set)' : 'Not set' ?>"></div>
        <button class="btn btn-primary btn-sm" type="submit">Save Microsoft credentials</button>
      </form>
    </div>
  </div>

  <!-- Court hearing reminders -->
  <div class="card card-pad">
    <div class="card-head"><h3>Court hearing reminders</h3></div>
    <p class="case-client" style="margin-bottom:14px">How far ahead should the nightly cron create a reminder task before a hearing?</p>
    <form method="post" action="<?= url('settings') ?>" style="display:flex;flex-wrap:wrap;gap:8px">
      <?= csrf_field() ?><input type="hidden" name="_form" value="hearing">
      <button type="submit" name="hearing_reminder_offset_days" value="1" class="filter-chip <?= $offset === 1 ? 'active' : '' ?>">Tomorrow <span style="font-weight:400">(1 day before)</span></button>
      <button type="submit" name="hearing_reminder_offset_days" value="2" class="filter-chip <?= $offset === 2 ? 'active' : '' ?>">Day after tomorrow <span style="font-weight:400">(2 days before)</span></button>
    </form>
    <div class="alert alert-info" style="margin-top:14px;margin-bottom:0">
      Schedule <code>cron/hearing_reminders.php</code> daily and <code>cron/calendar_sync.php</code> every 10–15 min.
    </div>
  </div>

  <!-- Team & roles -->
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
              <select class="input" name="role" onchange="this.form.submit()" <?= (int)$t['id']===(int)$currentUser['uid']?'disabled':'' ?>>
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

<script>
(function () {
  var tabs = document.querySelectorAll('[data-settings-tab]');
  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
      btn.classList.add('active'); btn.setAttribute('aria-selected', 'true');
      document.querySelectorAll('[data-settings-panel]').forEach(function (panel) {
        panel.style.display = panel.getAttribute('data-settings-panel') === btn.getAttribute('data-settings-tab') ? '' : 'none';
      });
    });
  });
})();
</script>

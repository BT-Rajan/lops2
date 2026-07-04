<div class="page-head">
  <div><span class="eyebrow-gold">Your seat</span><h1>My account</h1><p class="sub">Profile, password and sign-in email.</p></div>
</div>
<div class="profile-grid">
  <div class="card profile-card">
    <span class="avatar" style="background:<?= htmlspecialchars($profileUser['avatar_color'] ?? '#3B6FE0') ?>">
      <?= htmlspecialchars(initials($profileUser['full_name'] ?? $profileUser['email'])) ?>
    </span>
    <h3><?= htmlspecialchars($profileUser['full_name'] ?: '—') ?></h3>
    <div class="role"><?= htmlspecialchars($profileUser['job_title'] ?: ($profileUser['role'] ?? 'member')) ?></div>
    <div class="meta-row"><span>Email</span><span><?= htmlspecialchars($profileUser['email']) ?></span></div>
    <div class="meta-row"><span>Member since</span><span><?= date('d M Y', strtotime($profileUser['dt'])) ?></span></div>
    <div class="meta-row"><span>Role</span><span><?= htmlspecialchars($profileUser['role'] ?? 'member') ?></span></div>
  </div>
  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card card-pad">
      <div class="card-head"><h3>Profile</h3></div>
      <form method="post" action="<?= url('profile') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="profile">
        <div class="input-row">
          <div class="field"><label>Full name</label><input class="input" type="text" name="full_name" value="<?= htmlspecialchars($profileUser['full_name'] ?? '') ?>" required></div>
          <div class="field"><label>Role at the firm</label><input class="input" type="text" name="job_title" value="<?= htmlspecialchars($profileUser['job_title'] ?? '') ?>"></div>
        </div>
        <div class="field">
          <label>Avatar colour</label>
          <input type="color" name="avatar_color" value="<?= htmlspecialchars($profileUser['avatar_color'] ?? '#3B6FE0') ?>" style="width:60px;height:38px;border:1.5px solid var(--line);border-radius:8px;background:none">
        </div>
        <button class="btn btn-primary" type="submit">Save profile</button>
      </form>
    </div>
    <div class="card card-pad">
      <div class="card-head"><h3>Change password</h3></div>
      <form method="post" action="<?= url('profile') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="password">
        <div class="field"><label>Current password</label><input class="input" type="password" name="current_password" required></div>
        <div class="input-row">
          <div class="field"><label>New password</label><input class="input" type="password" name="new_password" minlength="8" required></div>
          <div class="field"><label>Confirm</label><input class="input" type="password" name="new_password_confirm" minlength="8" required></div>
        </div>
        <button class="btn btn-primary" type="submit">Update password</button>
      </form>
    </div>
    <div class="card card-pad">
      <div class="card-head"><h3>Change email</h3></div>
      <form method="post" action="<?= url('profile') ?>">
        <?= csrf_field() ?><input type="hidden" name="_form" value="email">
        <div class="input-row">
          <div class="field"><label>New email</label><input class="input" type="email" name="new_email" required></div>
          <div class="field"><label>Confirm with password</label><input class="input" type="password" name="confirm_password" required></div>
        </div>
        <button class="btn btn-ghost" type="submit">Update email</button>
      </form>
    </div>
  </div>
</div>

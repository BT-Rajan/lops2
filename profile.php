<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);

$page_title = 'Account settings';
$active_nav = 'profile';
$breadcrumb = [
    ['label' => 'Account settings'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');
        $avatarColor = preg_match('/^#[0-9A-Fa-f]{6}$/', $_POST['avatar_color'] ?? '') ? $_POST['avatar_color'] : '#3B6FE0';

        if ($fullName === '') {
            flash('error', 'Full name can\'t be empty.');
        } else {
            $auth->updateUser((int)$current_user['uid'], [
                'full_name' => $fullName,
                'job_title' => $jobTitle,
                'avatar_color' => $avatarColor,
            ]);
            flash('success', 'Profile updated.');
        }
    } elseif ($form === 'password') {
        $result = $auth->changePassword(
            (int)$current_user['uid'],
            (string)($_POST['current_password'] ?? ''),
            (string)($_POST['new_password'] ?? ''),
            (string)($_POST['new_password_confirm'] ?? '')
        );
        flash($result['error'] ? 'error' : 'success', $result['message']);
    } elseif ($form === 'email') {
        $result = $auth->changeEmail(
            (int)$current_user['uid'],
            trim($_POST['new_email'] ?? ''),
            (string)($_POST['confirm_password'] ?? '')
        );
        flash($result['error'] ? 'error' : 'success', $result['message']);
    }

    header('Location: ' . base_url('profile.php'));
    exit;
}

// Re-fetch in case it just changed
$current_user = $auth->getUser((int)$current_user['uid']);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Your seat</span>
    <h1>Account settings</h1>
    <p class="sub">Update your profile, password and sign-in email.</p>
  </div>
</div>

<div class="profile-grid">
  <div class="card profile-card">
    <span class="avatar" style="background:<?= htmlspecialchars($current_user['avatar_color'] ?? '#3B6FE0') ?>">
      <?= htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'])) ?>
    </span>
    <h3><?= htmlspecialchars($current_user['full_name'] ?: 'Unnamed user') ?></h3>
    <div class="role"><?= htmlspecialchars($current_user['job_title'] ?: 'Team member') ?></div>
    <div class="meta-row"><span>Email</span><span><?= htmlspecialchars($current_user['email']) ?></span></div>
    <div class="meta-row"><span>Member since</span><span><?= date('d M Y', strtotime($current_user['dt'])) ?></span></div>
    <div class="meta-row"><span>Status</span><span><?= $current_user['isactive'] ? 'Active' : 'Inactive' ?></span></div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <div class="card card-pad">
      <div class="card-head"><h3>Profile</h3></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="profile">
        <div class="input-row">
          <div class="field">
            <label>Full name</label>
            <input class="input" type="text" name="full_name" value="<?= htmlspecialchars($current_user['full_name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Role at the firm</label>
            <input class="input" type="text" name="job_title" value="<?= htmlspecialchars($current_user['job_title'] ?? '') ?>">
          </div>
        </div>
        <div class="field">
          <label>Avatar colour</label>
          <input type="color" name="avatar_color" value="<?= htmlspecialchars($current_user['avatar_color'] ?? '#3B6FE0') ?>" style="width:60px;height:38px;border:1.5px solid var(--line);border-radius:8px;background:none">
        </div>
        <button class="btn btn-primary" type="submit">Save profile</button>
      </form>
    </div>

    <div class="card card-pad">
      <div class="card-head"><h3>Change password</h3></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">
        <div class="field">
          <label>Current password</label>
          <input class="input" type="password" name="current_password" required>
        </div>
        <div class="input-row">
          <div class="field">
            <label>New password</label>
            <input class="input" type="password" name="new_password" minlength="8" required>
          </div>
          <div class="field">
            <label>Confirm new password</label>
            <input class="input" type="password" name="new_password_confirm" minlength="8" required>
          </div>
        </div>
        <button class="btn btn-primary" type="submit">Update password</button>
      </form>
    </div>

    <div class="card card-pad">
      <div class="card-head"><h3>Change sign-in email</h3></div>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="email">
        <div class="input-row">
          <div class="field">
            <label>New email</label>
            <input class="input" type="email" name="new_email" required>
          </div>
          <div class="field">
            <label>Confirm with password</label>
            <input class="input" type="password" name="confirm_password" required>
          </div>
        </div>
        <button class="btn btn-ghost" type="submit">Update email</button>
      </form>
    </div>

  </div>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

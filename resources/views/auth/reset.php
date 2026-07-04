<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<span class="eyebrow">Account recovery</span>
<h2>Set a new password</h2>

<?php if ($success): ?>
  <div class="alert alert-success">Password updated — you can sign in now.</div>
  <a class="btn btn-primary btn-block" href="<?= url('login') ?>">Go to sign in</a>
<?php elseif (!$token): ?>
  <div class="alert alert-error">Missing reset token — request a new link.</div>
  <a class="btn btn-ghost btn-block" href="<?= url('forgot-password') ?>">Request a new link</a>
<?php else: ?>
  <form method="post" action="<?= url('reset-password') ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <div class="field">
      <label>New password</label>
      <input class="input" type="password" name="password" placeholder="8+ characters" required minlength="8" autofocus>
    </div>
    <div class="field">
      <label>Confirm new password</label>
      <input class="input" type="password" name="password_confirm" required minlength="8">
    </div>
    <button class="btn btn-primary btn-block" type="submit">Update password</button>
  </form>
<?php endif; ?>

<p class="auth-foot"><a href="<?= url('login') ?>">← Back to sign in</a></p>

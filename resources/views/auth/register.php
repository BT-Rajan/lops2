<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<span class="eyebrow">Get started</span>
<h2>Create your account</h2>
<p class="sub">You'll be able to join or start a firm workspace immediately.</p>

<form method="post" action="<?= url('register') ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label>Full name</label>
    <input class="input" type="text" name="full_name" placeholder="Your full name" required autofocus value="<?= htmlspecialchars($old['full_name'] ?? '') ?>">
  </div>
  <div class="field">
    <label>Role at the firm</label>
    <input class="input" type="text" name="job_title" placeholder="Associate, Partner, Paralegal…" value="<?= htmlspecialchars($old['job_title'] ?? '') ?>">
  </div>
  <div class="field">
    <label>Work email</label>
    <input class="input" type="email" name="email" placeholder="you@yourfirm.com" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
  </div>
  <div class="input-row">
    <div class="field">
      <label>Password</label>
      <input class="input" type="password" name="password" placeholder="8+ characters" required minlength="8">
    </div>
    <div class="field">
      <label>Confirm password</label>
      <input class="input" type="password" name="password_confirm" placeholder="Repeat" required minlength="8">
    </div>
  </div>
  <button class="btn btn-primary btn-block" type="submit">Create account</button>
</form>
<p class="auth-foot">Already on LegalOps? <a href="<?= url('login') ?>">Sign in</a></p>

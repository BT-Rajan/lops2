<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<span class="eyebrow">Account recovery</span>
<h2>Forgot your password?</h2>
<p class="sub">Enter your email and we'll generate a one-hour reset link.</p>

<?php if ($resetLink): ?>
  <div class="alert alert-success">Reset link generated — valid for 1 hour.</div>
  <div class="reset-token-box">
    <strong>Local dev note:</strong> no SMTP configured, so the link is shown here.<br><br>
    <a href="<?= htmlspecialchars($resetLink) ?>"><?= htmlspecialchars($resetLink) ?></a>
  </div>
<?php else: ?>
  <form method="post" action="<?= url('forgot-password') ?>" novalidate>
    <?= csrf_field() ?>
    <div class="field">
      <label>Account email</label>
      <input class="input" type="email" name="email" placeholder="you@yourfirm.com" required autofocus>
    </div>
    <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
  </form>
<?php endif; ?>

<p class="auth-foot"><a href="<?= url('login') ?>">← Back to sign in</a></p>

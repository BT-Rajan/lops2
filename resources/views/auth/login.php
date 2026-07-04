<?php if ($error): ?>
  <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<span class="eyebrow">Welcome back</span>
<h2>Sign in to your firm</h2>
<p class="sub">Enter your credentials to access the practice dashboard.</p>

<form method="post" action="<?= url('login') ?>" novalidate>
  <?= csrf_field() ?>
  <div class="field">
    <label for="email">Work email</label>
    <input class="input" type="email" id="email" name="email" placeholder="you@yourfirm.com" required autofocus value="<?= htmlspecialchars($email ?? '') ?>">
  </div>
  <div class="field">
    <label for="password">Password</label>
    <input class="input" type="password" id="password" name="password" placeholder="••••••••" required>
  </div>
  <div class="check-row">
    <label><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <a href="<?= url('forgot-password') ?>">Forgot password?</a>
  </div>
  <button class="btn btn-primary btn-block" type="submit">Sign in</button>
</form>

<div class="reset-token-box" style="margin-top:18px;background:var(--accent-100);border-color:var(--accent-600)">
  <strong>Demo account</strong> — demo@legalops.local / LegalOps@123
</div>

<p class="auth-foot">New to LegalOps? <a href="<?= url('register') ?>">Create an account</a></p>

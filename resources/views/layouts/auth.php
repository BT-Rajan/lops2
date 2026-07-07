<?php require_once dirname(__DIR__, 3) . '/libs/icons.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
</head>
<body>
<div class="auth-shell">
  <div class="auth-brandpane">
    <div class="brand-mark">
      <span class="glyph"><?= icon('scales') ?></span>
      <span class="brand-wordmark">Legal<span>Ops</span></span>
    </div>
    <div class="pitch">
      <h1><?= $brandHeadline ?? 'Practice management, run the way a good firm runs.' ?></h1>
      <p><?= $brandSub ?? 'Cases, clients, deadlines and billing — one quiet, well-kept ledger for the whole practice.' ?></p>
    </div>
    <div class="brand-stats">
      <div class="stat"><b>256-bit</b><span>bcrypt hashing</span></div>
      <div class="stat"><b>0</b><span>Third parties</span></div>
      <div class="stat"><b>100%</b><span>Self-hosted</span></div>
    </div>
  </div>
  <div class="auth-formpane">
    <div class="auth-card">
      <?php if ($flash = flash_get()): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-flash><?= htmlspecialchars($flash['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>

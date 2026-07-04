<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($title ?? 'Error') ?></title>
<link rel="stylesheet" href="<?= url('assets/css/app.css') ?>">
</head>
<body style="display:grid;place-items:center;min-height:100vh">
<div style="text-align:center;max-width:480px;padding:24px">
  <div style="font-size:64px;font-family:var(--font-display);color:var(--text-muted);margin-bottom:16px">⚖</div>
  <?= $content ?? '<h1>Something went wrong</h1>' ?>
  <a class="btn btn-ghost" href="<?= url('dashboard') ?>" style="margin-top:24px;display:inline-flex">← Back to dashboard</a>
</div>
</body>
</html>

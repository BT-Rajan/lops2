<?php
$theme = ($_COOKIE['legalops_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
require_once dirname(__DIR__, 3) . '/libs/icons.php';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(($pageTitle ?? 'Dashboard') . ' · ' . APP_NAME) ?></title>
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<script>window.APP_BASE = <?= json_encode(url('')) ?>;</script>
</head>
<body>
<div class="scrim"></div>
<div class="app-shell">

<?php \Lops2\Core\View::partial('nav', ['currentUser' => $currentUser, 'activeNav' => $activeNav ?? '', 'bellCount' => $bellCount ?? 0]) ?>

  <div class="main">
    <?php \Lops2\Core\View::partial('topbar', ['currentUser' => $currentUser, 'pageTitle' => $pageTitle ?? '', 'bellCount' => $bellCount ?? 0]) ?>
    <div class="content">
      <?php if ($flash = flash_get()): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" data-flash><?= htmlspecialchars($flash['message']) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </div>

</div>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>

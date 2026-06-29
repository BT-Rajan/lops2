<?php
/**
 * Expects $auth, $pdo (from bootstrap) and optionally:
 *   $page_title   – shown in <title> and as a fallback heading
 *   $active_nav   – one of the keys in $nav_items below, for highlighting
 */
require_once __DIR__ . '/icons.php';

$current_user = $auth->getCurrentUser();
$theme = ($_COOKIE['legalops_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';

$nav_items = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'dashboard.php'],
    'cases'     => ['label' => 'Cases',     'icon' => 'cases',     'href' => 'cases.php'],
    'clients'   => ['label' => 'Clients',   'icon' => 'clients',   'href' => 'clients.php'],
    'tasks'     => ['label' => 'Tasks',     'icon' => 'tasks',     'href' => 'modules.php?m=tasks', 'soon' => true],
    'calendar'  => ['label' => 'Calendar',  'icon' => 'calendar',  'href' => 'modules.php?m=calendar', 'soon' => true],
    'documents' => ['label' => 'Documents', 'icon' => 'documents', 'href' => 'documents.php'],
    'billing'   => ['label' => 'Billing',   'icon' => 'billing',   'href' => 'billing.php'],
];
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(($page_title ?? 'Dashboard') . ' · ' . APP_NAME) ?></title>
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<script>window.APP_BASE_PATH = <?= json_encode(base_url('')) ?>;</script>
</head>
<body>
<div class="scrim" data-menu-toggle></div>
<div class="app-shell">

  <aside class="rail">
    <div class="brand-mark">
      <span class="glyph"><?= icon('scales') ?></span>
      <span class="brand-wordmark">Legal<span>Ops</span></span>
    </div>

    <nav class="nav-group">
      <?php foreach ($nav_items as $key => $item): ?>
        <a class="nav-item <?= ($active_nav ?? '') === $key ? 'active' : '' ?>" href="<?= base_url($item['href']) ?>">
          <?= icon($item['icon']) ?>
          <span><?= htmlspecialchars($item['label']) ?></span>
          <?php if (!empty($item['soon'])): ?><span class="soon">Soon</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="nav-group" style="margin-top:auto">
      <a class="nav-item <?= ($active_nav ?? '') === 'profile' ? 'active' : '' ?>" href="<?= base_url('profile.php') ?>">
        <?= icon('settings') ?><span>Account settings</span>
      </a>
      <a class="nav-item" href="<?= base_url('logout.php') ?>">
        <?= icon('logout') ?><span>Sign out</span>
      </a>
    </div>

    <div class="rail-foot">
      <div class="rail-user">
        <span class="avatar" style="background:<?= htmlspecialchars($current_user['avatar_color'] ?? '#3B6FE0') ?>">
          <?= htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'] ?? '?')) ?>
        </span>
        <div>
          <div class="name"><?= htmlspecialchars($current_user['full_name'] ?? 'Your account') ?></div>
          <div class="role"><?= htmlspecialchars($current_user['job_title'] ?? 'Team member') ?></div>
        </div>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-toggle" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>
      <form class="search-pill" action="<?= base_url('cases.php') ?>" method="get" autocomplete="off" data-search-form>
        <?= icon('search') ?>
        <input data-global-search name="q" style="border:none;outline:none;background:transparent;font:inherit;color:inherit;width:100%" type="text" placeholder="Search cases, clients, tasks…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <kbd>/</kbd>
        <div class="search-dropdown" data-search-dropdown></div>
      </form>
      <div class="topbar-actions">
        <button class="icon-btn" data-theme-toggle aria-label="Toggle theme"><?= icon('sun') ?></button>
        <button class="icon-btn" aria-label="Notifications"><?= icon('bell') ?></button>
        <a href="<?= base_url('profile.php') ?>" class="avatar avatar-sm" style="background:<?= htmlspecialchars($current_user['avatar_color'] ?? '#3B6FE0') ?>">
          <?= htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'] ?? '?')) ?>
        </a>
      </div>
    </header>

    <div class="content">
      <?php $__flash = flash_get(); if ($__flash): ?>
        <div class="alert alert-<?= htmlspecialchars($__flash['type']) ?>" data-flash><?= htmlspecialchars($__flash['message']) ?></div>
      <?php endif; ?>

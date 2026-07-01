<?php
/**
 * Expects $auth, $pdo (from bootstrap) and optionally:
 *   $page_title   – shown in <title> and topbar breadcrumb
 *   $active_nav   – one of the keys in $nav_items below, for highlighting
 *   $breadcrumb   – array of ['label'=>'...','href'=>'...'] trail items
 *                   e.g. [['label'=>'Cases','href'=>'cases.php'],['label'=>$case['title']]]
 *                   Last item is the current page (no href needed).
 */
require_once __DIR__ . '/icons.php';

$current_user = $auth->getCurrentUser();
$theme = ($_COOKIE['legalops_theme'] ?? 'light') === 'dark' ? 'dark' : 'light';

// Open task count for the bell badge — scoped to the current user
// unless they're an admin, same access rule as everywhere else.
$_bell_uid = (int)($current_user['uid'] ?? 0);
if (is_admin($current_user)) {
    $_bell_count = (int)$pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done'")->fetchColumn();
} else {
    $_bell_stmt = $pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done' AND (assigned_to = ? OR created_by = ?)");
    $_bell_stmt->execute([$_bell_uid, $_bell_uid]);
    $_bell_count = (int)$_bell_stmt->fetchColumn();
}

$nav_items = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'dashboard', 'href' => 'dashboard.php'],
    'cases'     => ['label' => 'Cases',     'icon' => 'cases',     'href' => 'cases.php'],
    'clients'   => ['label' => 'Clients',   'icon' => 'clients',   'href' => 'clients.php'],
    'tasks'     => ['label' => 'Tasks',     'icon' => 'tasks',     'href' => 'tasks.php'],
    'calendar'  => ['label' => 'Calendar',  'icon' => 'calendar',  'href' => 'calendar.php'],
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
      <?php if (is_admin($current_user)): ?>
      <a class="nav-item <?= ($active_nav ?? '') === 'settings' ? 'active' : '' ?>" href="<?= base_url('settings.php') ?>">
        <?= icon('settings') ?><span>Firm settings</span>
      </a>
      <?php endif; ?>
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
        <input data-global-search name="q"
          style="border:none;outline:none;background:transparent;font:inherit;color:inherit;width:100%"
          type="text"
          placeholder="Search cases, clients, tasks, documents…"
          value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <kbd title="Press / to focus search">/</kbd>
        <div class="search-dropdown" data-search-dropdown></div>
      </form>

      <div class="topbar-actions">
        <!-- Theme toggle: shows sun in dark mode, moon in light mode -->
        <button class="icon-btn theme-toggle-btn" data-theme-toggle aria-label="Toggle theme">
          <span class="icon-light"><?= icon('moon') ?></span>
          <span class="icon-dark"><?= icon('sun') ?></span>
        </button>

        <!-- Bell: shows open task count, links to cases page filtered to open tasks -->
        <a href="<?= base_url('cases.php?status=open') ?>" class="icon-btn notif-btn" aria-label="<?= $_bell_count ?> open tasks" style="position:relative;text-decoration:none">
          <?= icon('bell') ?>
          <?php if ($_bell_count > 0): ?>
            <span class="notif-badge"><?= $_bell_count > 99 ? '99+' : $_bell_count ?></span>
          <?php endif; ?>
        </a>

        <a href="<?= base_url('profile.php') ?>" class="avatar avatar-sm" style="background:<?= htmlspecialchars($current_user['avatar_color'] ?? '#3B6FE0') ?>">
          <?= htmlspecialchars(initials($current_user['full_name'] ?? $current_user['email'] ?? '?')) ?>
        </a>
      </div>
    </header>

    <div class="content">
      <?php $__flash = flash_get(); if ($__flash): ?>
        <div class="alert alert-<?= htmlspecialchars($__flash['type']) ?>" data-flash><?= htmlspecialchars($__flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!empty($breadcrumb)): ?>
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a class="bc-item" href="<?= base_url('dashboard.php') ?>"><?= icon('dashboard') ?></a>
          <?php foreach ($breadcrumb as $i => $bc): ?>
            <span class="bc-sep">›</span>
            <?php if (!empty($bc['href']) && $i < count($breadcrumb) - 1): ?>
              <a class="bc-item" href="<?= base_url($bc['href']) ?>"><?= htmlspecialchars($bc['label']) ?></a>
            <?php else: ?>
              <span class="bc-item bc-current"><?= htmlspecialchars($bc['label']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

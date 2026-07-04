<header class="topbar">
  <button class="icon-btn menu-toggle" data-menu-toggle aria-label="Menu"><?= icon('menu') ?></button>

  <form class="search-pill" action="<?= url('cases') ?>" method="get" autocomplete="off" data-search-form>
    <?= icon('search') ?>
    <input data-global-search name="q" style="border:none;outline:none;background:transparent;font:inherit;color:inherit;width:100%"
           type="text" placeholder="Search cases, clients, tasks…" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
    <kbd>/</kbd>
    <div class="search-dropdown" data-search-dropdown></div>
  </form>

  <div class="topbar-actions">
    <button class="icon-btn" data-theme-toggle aria-label="Toggle theme"><?= icon('sun') ?></button>
    <a class="icon-btn" href="<?= url('tasks?status=pending') ?>" aria-label="Open tasks" style="position:relative">
      <?= icon('bell') ?>
      <?php if (($bellCount ?? 0) > 0): ?>
        <span style="position:absolute;top:6px;right:6px;width:8px;height:8px;border-radius:50%;background:var(--danger);border:2px solid var(--surface-topbar)"></span>
      <?php endif; ?>
    </a>
    <a href="<?= url('profile') ?>" class="avatar avatar-sm" style="background:<?= htmlspecialchars($currentUser['avatar_color'] ?? '#3B6FE0') ?>">
      <?= htmlspecialchars(initials($currentUser['full_name'] ?? $currentUser['email'] ?? '?')) ?>
    </a>
  </div>
</header>

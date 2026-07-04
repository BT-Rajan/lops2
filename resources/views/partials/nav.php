<?php
$nav = [
    'work' => [
        'dashboard' => ['label' => 'Dashboard',  'icon' => 'dashboard', 'href' => 'dashboard'],
        'cases'     => ['label' => 'Cases',       'icon' => 'cases',     'href' => 'cases'],
        'clients'   => ['label' => 'Clients',     'icon' => 'clients',   'href' => 'clients'],
        'tasks'     => ['label' => 'Tasks',       'icon' => 'tasks',     'href' => 'tasks'],
        'calendar'  => ['label' => 'Calendar',    'icon' => 'calendar',  'href' => 'calendar'],
    ],
    'finance' => [
        'billing'   => ['label' => 'Billing',     'icon' => 'billing',   'href' => 'billing'],
    ],
    'system' => [],
];
if (is_admin($currentUser)) {
    $nav['system']['settings'] = ['label' => 'Firm settings', 'icon' => 'settings', 'href' => 'settings'];
}
$nav['system']['profile'] = ['label' => 'My account',    'icon' => 'settings', 'href' => 'profile'];
?>
<aside class="rail">
  <a class="brand-mark" href="<?= url('dashboard') ?>" style="text-decoration:none">
    <span class="glyph"><?= icon('scales') ?></span>
    <span class="brand-wordmark">Legal<span>Ops</span></span>
  </a>

  <nav>
    <div class="nav-group">
      <div class="nav-label">Matters</div>
      <?php foreach ($nav['work'] as $key => $item): ?>
      <a class="nav-item <?= ($activeNav === $key) ? 'active' : '' ?>" href="<?= url($item['href']) ?>">
        <?= icon($item['icon']) ?> <span><?= htmlspecialchars($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="nav-group">
      <div class="nav-label">Finance</div>
      <?php foreach ($nav['finance'] as $key => $item): ?>
      <a class="nav-item <?= ($activeNav === $key) ? 'active' : '' ?>" href="<?= url($item['href']) ?>">
        <?= icon($item['icon']) ?> <span><?= htmlspecialchars($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($nav['system'])): ?>
    <div class="nav-group" style="margin-top:auto;padding-top:8px">
      <div class="nav-label">System</div>
      <?php foreach ($nav['system'] as $key => $item): ?>
      <a class="nav-item <?= ($activeNav === $key) ? 'active' : '' ?>" href="<?= url($item['href']) ?>">
        <?= icon($item['icon']) ?> <span><?= htmlspecialchars($item['label']) ?></span>
      </a>
      <?php endforeach; ?>
      <a class="nav-item" href="<?= url('logout') ?>"><?= icon('logout') ?> <span>Sign out</span></a>
    </div>
    <?php endif; ?>
  </nav>

  <div class="rail-foot">
    <a class="rail-user" href="<?= url('profile') ?>" style="text-decoration:none">
      <span class="avatar" style="background:<?= htmlspecialchars($currentUser['avatar_color'] ?? '#3B6FE0') ?>">
        <?= htmlspecialchars(initials($currentUser['full_name'] ?? $currentUser['email'] ?? '?')) ?>
      </span>
      <div>
        <div class="name"><?= htmlspecialchars($currentUser['full_name'] ?? 'My account') ?></div>
        <div class="role"><?= htmlspecialchars($currentUser['job_title'] ?? ($currentUser['role'] ?? 'member')) ?></div>
      </div>
    </a>
  </div>
</aside>

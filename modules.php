<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);

$modules = [
    'clients'   => ['label' => 'Clients',   'icon' => 'clients',   'blurb' => 'A full client directory — contact details, matters, and billing history in one place.'],
    'tasks'     => ['label' => 'Tasks',     'icon' => 'tasks',     'blurb' => 'Firm-wide task board with assignments, due dates and reminders.'],
    'calendar'  => ['label' => 'Calendar',  'icon' => 'calendar',  'blurb' => 'Hearings, filings and deadlines on a shared firm calendar.'],
    'documents' => ['label' => 'Documents', 'icon' => 'documents', 'blurb' => 'Versioned document storage tied to each matter.'],
];

$key = $_GET['m'] ?? 'clients';
if (!isset($modules[$key])) {
    $key = 'clients';
}
$m = $modules[$key];

$page_title = $m['label'];
$active_nav = $key;

require __DIR__ . '/includes/app_header.php';
?>

<div class="coming-soon">
  <div>
    <span class="glyph"><?= icon($m['icon']) ?></span>
    <h2><?= htmlspecialchars($m['label']) ?> is on the roadmap</h2>
    <p><?= htmlspecialchars($m['blurb']) ?> This part of LegalOps hasn't been built yet — happy to wire it up next.</p>
    <div style="margin-top:22px">
      <a class="btn btn-primary" href="<?= base_url('dashboard.php') ?>">Back to dashboard</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

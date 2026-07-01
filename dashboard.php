<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];
$isAdmin = is_admin($current_user);

$page_title = 'Dashboard';
$active_nav = 'dashboard';

$openCases    = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'open'")->fetchColumn();
$pendingCases = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'pending'")->fetchColumn();
$closedCases  = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'closed'")->fetchColumn();

// Month-over-month deltas for KPI cards
$openedThisMonth = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
$openedLastMonth = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH,'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
$tasksThisMonth  = (int)$pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();
$tasksLastMonth  = (int)$pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH,'%Y-%m-01') AND created_at < DATE_FORMAT(NOW(),'%Y-%m-01')")->fetchColumn();

function delta_info(int $now, int $prev): array {
    if ($prev === 0 && $now === 0) return ['flat', '—', 'kpi-flat'];
    if ($prev === 0) return ['up', '+' . $now . ' vs last month', 'kpi-up'];
    $diff = $now - $prev;
    if ($diff > 0) return ['up', '+' . $diff . ' vs last month', 'kpi-up'];
    if ($diff < 0) return ['down', $diff . ' vs last month', 'kpi-down'];
    return ['flat', 'Same as last month', 'kpi-flat'];
}
[$openDir,  $openDelta,  $openClass]  = delta_info($openCases,      $openedLastMonth);
[$closeDir, $closeDelta, $closeClass] = delta_info($closedCases,     0);
[$taskDir,  $taskDelta,  $taskClass]  = delta_info($tasksThisMonth,  $tasksLastMonth);

// Task figures scoped to current user unless admin
$taskScopeSql        = $isAdmin ? '' : ' AND (assigned_to = ? OR created_by = ?)';
$taskScopeSqlAliased = $isAdmin ? '' : ' AND (t.assigned_to = ? OR t.created_by = ?)';
$taskScopeParams     = $isAdmin ? [] : [$uid, $uid];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done'" . $taskScopeSql);
$stmt->execute($taskScopeParams);
$openTasks = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done' AND due_on <= CURDATE() + INTERVAL 7 DAY" . $taskScopeSql);
$stmt->execute($taskScopeParams);
$dueSoon = (int)$stmt->fetchColumn();

$recentCases = $pdo->query(
    "SELECT * FROM legalops_cases ORDER BY created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    "SELECT t.*, c.title AS case_title FROM legalops_tasks t
     LEFT JOIN legalops_cases c ON c.id = t.case_id
     WHERE 1=1" . $taskScopeSqlAliased . "
     ORDER BY (t.status='done') ASC, t.due_on ASC LIMIT 6"
);
$stmt->execute($taskScopeParams);
$upcomingTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Activity feed: admins see firm-wide (with user names); members see only their own actions.
if ($isAdmin) {
    $activity = $pdo->query(
        "SELECT a.*, u.full_name FROM legalops_activity a
         LEFT JOIN phpauth_users u ON u.id = a.uid
         ORDER BY a.created_at DESC LIMIT 20"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare(
        "SELECT a.*, u.full_name FROM legalops_activity a
         LEFT JOIN phpauth_users u ON u.id = a.uid
         WHERE a.uid = ? ORDER BY a.created_at DESC LIMIT 10"
    );
    $stmt->execute([$uid]);
    $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Bucket actions for the filter tabs
$actionGroups = [
    'cases'     => ['case_created','case_updated','case_status','case_deleted'],
    'tasks'     => ['task_created','task_done'],
    'documents' => ['document_uploaded','document_deleted'],
    'clients'   => ['client_created','client_updated','client_deleted','document_uploaded'],
];

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Good to see you</span>
    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $current_user['full_name'] ?? 'there')[0]) ?>.</h1>
    <p class="sub">Here's where the practice stands today, <?= date('l, j F Y') ?>.</p>
  </div>
</div>

<!-- Quick actions -->
<div class="quick-actions">
  <a class="qa-btn" href="<?= base_url('cases.php') ?>" id="qa-new-case" onclick="event.preventDefault();document.getElementById('qa-new-case').closest('.quick-actions').insertAdjacentHTML('afterend','');window.location='<?= base_url('cases.php') ?>#new'">
    <?= icon('plus') ?> New matter
  </a>
  <a class="qa-btn" href="<?= base_url('clients.php') ?>#new">
    <?= icon('clients') ?> New client
  </a>
  <a class="qa-btn" href="<?= base_url('billing.php') ?>#new">
    <?= icon('billing') ?> New invoice
  </a>
  <a class="qa-btn" href="<?= base_url('documents.php') ?>">
    <?= icon('documents') ?> Browse documents
  </a>
</div>

<!-- KPI cards — each links to a filtered view -->
<div class="kpi-grid">
  <a class="card kpi-card" href="<?= base_url('cases.php?status=open') ?>">
    <span class="kpi-icon icon-tint-blue"><?= icon('briefcase') ?></span>
    <div class="kpi-value"><?= $openCases ?></div>
    <div class="kpi-label">Open matters</div>
    <span class="kpi-delta <?= $openClass ?>"><?= $openDir === 'up' ? icon('arrow-up') : ($openDir === 'down' ? icon('arrow-down') : '') ?> <?= htmlspecialchars($openDelta) ?></span>
  </a>
  <a class="card kpi-card" href="<?= base_url('cases.php?status=pending') ?>">
    <span class="kpi-icon icon-tint-brass"><?= icon('clock') ?></span>
    <div class="kpi-value"><?= $pendingCases ?></div>
    <div class="kpi-label">Pending matters</div>
    <span class="kpi-delta kpi-flat">Awaiting next step</span>
  </a>
  <a class="card kpi-card" href="<?= base_url('cases.php?status=closed') ?>">
    <span class="kpi-icon icon-tint-green"><?= icon('check') ?></span>
    <div class="kpi-value"><?= $closedCases ?></div>
    <div class="kpi-label">Closed matters</div>
    <span class="kpi-delta kpi-flat"><?= $openedThisMonth ?> opened this month</span>
  </a>
  <a class="card kpi-card" href="<?= base_url('cases.php') ?>">
    <span class="kpi-icon icon-tint-red"><?= icon('flag') ?></span>
    <div class="kpi-value"><?= $openTasks ?></div>
    <div class="kpi-label">Open tasks</div>
    <span class="kpi-delta <?= $dueSoon > 0 ? 'kpi-down' : 'kpi-flat' ?>"><?= $dueSoon > 0 ? icon('arrow-down') : '' ?> <?= $dueSoon ?> due within 7 days</span>
  </a>
</div>

<div class="grid-2">
  <div class="card card-pad">
    <div class="card-head">
      <h3>Recent matters</h3>
      <a class="link" href="<?= base_url('cases.php') ?>">View all →</a>
    </div>
    <?php if ($recentCases): ?>
    <table class="table">
      <thead>
        <tr><th>Matter</th><th>Client</th><th>Status</th><th>Priority</th><th>Due</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentCases as $c): ?>
        <tr style="cursor:pointer" onclick="window.location='<?= base_url('case-view.php?id=' . (int)$c['id']) ?>'">
          <td>
            <div class="case-title"><?= htmlspecialchars($c['title']) ?></div>
            <div class="case-number"><?= htmlspecialchars($c['case_number']) ?></div>
          </td>
          <td class="case-client"><?= htmlspecialchars($c['client_name']) ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
          <td><span class="badge badge-<?= htmlspecialchars($c['priority']) ?>"><?= htmlspecialchars($c['priority']) ?></span></td>
          <td class="case-client"><?= $c['due_on'] ? date('d M Y', strtotime($c['due_on'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state"><?= icon('briefcase') ?><p>No matters yet — open your first case.</p></div>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card card-pad">
      <div class="card-head">
        <h3>Upcoming tasks</h3>
        <a class="link" href="<?= base_url('tasks.php') ?>">View all →</a>
      </div>
      <?php if ($upcomingTasks): foreach ($upcomingTasks as $t): ?>
        <div class="task-row <?= $t['status'] === 'done' ? 'done' : '' ?>"
          <?= $t['case_id'] ? 'style="cursor:pointer" onclick="window.location=\'' . base_url('case-view.php?id=' . (int)$t['case_id']) . '\'"' : '' ?>>
          <span class="task-check"><?= $t['status'] === 'done' ? icon('check') : '' ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="task-meta"><?= htmlspecialchars($t['case_title'] ?? 'No matter linked') ?> · Due <?= $t['due_on'] ? date('d M', strtotime($t['due_on'])) : '—' ?></div>
          </div>
          <span class="badge badge-<?= htmlspecialchars($t['priority']) ?>" style="margin-left:auto"><?= htmlspecialchars($t['priority']) ?></span>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><?= icon('tasks') ?><p>Nothing on the list right now.</p></div>
      <?php endif; ?>
    </div>

    <div class="card card-pad">
      <div class="card-head"><h3>Recent activity</h3></div>

      <!-- Filter tabs — JS filters the list client-side, no reload -->
      <div class="feed-tabs" role="tablist" aria-label="Filter activity">
        <button class="feed-tab active" data-filter="all" role="tab">All</button>
        <button class="feed-tab" data-filter="cases"     role="tab">Matters</button>
        <button class="feed-tab" data-filter="tasks"     role="tab">Tasks</button>
        <button class="feed-tab" data-filter="documents" role="tab">Docs</button>
        <button class="feed-tab" data-filter="clients"   role="tab">Clients</button>
      </div>

      <div class="activity-feed" id="activity-feed">
        <?php
        // Map action → group for the data-action attribute
        $actionMap = [];
        foreach ($actionGroups as $group => $actions) {
            foreach ($actions as $a) { $actionMap[$a] = $group; }
        }
        ?>
        <?php if ($activity): foreach ($activity as $a):
          $group = $actionMap[$a['action']] ?? 'other';
        ?>
          <div class="activity-item" data-action="<?= htmlspecialchars($group) ?>">
            <span class="activity-dot"></span>
            <div>
              <p><?= htmlspecialchars($a['description']) ?></p>
              <time><?= htmlspecialchars($a['full_name'] ?? 'System') ?> · <?= time_ago($a['created_at']) ?></time>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><p>No activity logged yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  // Activity feed filter
  var tabs = document.querySelectorAll('.feed-tab');
  var feed = document.getElementById('activity-feed');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      var filter = tab.getAttribute('data-filter');
      feed.querySelectorAll('.activity-item').forEach(function (item) {
        if (filter === 'all' || item.getAttribute('data-action') === filter) {
          item.classList.remove('hidden');
        } else {
          item.classList.add('hidden');
        }
      });
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);

$page_title = 'Dashboard';
$active_nav = 'dashboard';

$openCases = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'open'")->fetchColumn();
$pendingCases = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'pending'")->fetchColumn();
$closedCases = (int)$pdo->query("SELECT COUNT(*) FROM legalops_cases WHERE status = 'closed'")->fetchColumn();
$openTasks = (int)$pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done'")->fetchColumn();
$dueSoon = (int)$pdo->query("SELECT COUNT(*) FROM legalops_tasks WHERE status != 'done' AND due_on <= CURDATE() + INTERVAL 7 DAY")->fetchColumn();

$recentCases = $pdo->query(
    "SELECT * FROM legalops_cases ORDER BY created_at DESC LIMIT 5"
)->fetchAll(PDO::FETCH_ASSOC);

$upcomingTasks = $pdo->query(
    "SELECT t.*, c.title AS case_title FROM legalops_tasks t
     LEFT JOIN legalops_cases c ON c.id = t.case_id
     ORDER BY (t.status='done') ASC, t.due_on ASC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

$activity = $pdo->query(
    "SELECT * FROM legalops_activity ORDER BY created_at DESC LIMIT 6"
)->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Good to see you</span>
    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $current_user['full_name'] ?? 'there')[0]) ?>.</h1>
    <p class="sub">Here's where the practice stands today, <?= date('l, j F Y') ?>.</p>
  </div>
  <a href="<?= base_url('cases.php') ?>" class="btn btn-primary"><?= icon('plus') ?> New case</a>
</div>

<div class="kpi-grid">
  <div class="card kpi-card">
    <span class="kpi-icon icon-tint-blue"><?= icon('briefcase') ?></span>
    <div class="kpi-value"><?= $openCases ?></div>
    <div class="kpi-label">Open matters</div>
    <span class="kpi-delta kpi-up"><?= icon('arrow-up') ?> Active caseload</span>
  </div>
  <div class="card kpi-card">
    <span class="kpi-icon icon-tint-brass"><?= icon('clock') ?></span>
    <div class="kpi-value"><?= $pendingCases ?></div>
    <div class="kpi-label">Pending matters</div>
    <span class="kpi-delta kpi-flat">Awaiting next step</span>
  </div>
  <div class="card kpi-card">
    <span class="kpi-icon icon-tint-green"><?= icon('check') ?></span>
    <div class="kpi-value"><?= $closedCases ?></div>
    <div class="kpi-label">Closed matters</div>
    <span class="kpi-delta kpi-up"><?= icon('arrow-up') ?> Resolved this year</span>
  </div>
  <div class="card kpi-card">
    <span class="kpi-icon icon-tint-red"><?= icon('flag') ?></span>
    <div class="kpi-value"><?= $openTasks ?></div>
    <div class="kpi-label">Open tasks</div>
    <span class="kpi-delta <?= $dueSoon > 0 ? 'kpi-down' : 'kpi-flat' ?>"><?= $dueSoon ?> due within 7 days</span>
  </div>
</div>

<div class="grid-2">
  <div class="card card-pad">
    <div class="card-head">
      <h3>Recent matters</h3>
      <a class="link" href="<?= base_url('cases.php') ?>">View all cases →</a>
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
      <div class="empty-state"><?= icon('briefcase') ?><p>No matters yet — open your first case to see it here.</p></div>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card card-pad">
      <div class="card-head">
        <h3>Upcoming tasks</h3>
        <a class="link" href="<?= base_url('modules.php?m=tasks') ?>">View all →</a>
      </div>
      <?php if ($upcomingTasks): foreach ($upcomingTasks as $t): ?>
        <div class="task-row <?= $t['status'] === 'done' ? 'done' : '' ?>" <?= $t['case_id'] ? 'style="cursor:pointer" onclick="window.location=\'' . base_url('case-view.php?id=' . (int)$t['case_id']) . '\'"' : '' ?>>
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
      <div class="activity-feed">
        <?php if ($activity): foreach ($activity as $a): ?>
          <div class="activity-item">
            <span class="activity-dot"></span>
            <div>
              <p><?= htmlspecialchars($a['description']) ?></p>
              <time><?= time_ago($a['created_at']) ?></time>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><p>No activity logged yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

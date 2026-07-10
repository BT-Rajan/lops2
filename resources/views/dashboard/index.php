<?php
function kpi_delta(int $now, int $prev): array {
    if ($prev === 0 && $now === 0) return ['—', 'kpi-flat'];
    if ($prev === 0)  return ['+' . $now . ' vs last month', 'kpi-up'];
    $d = $now - $prev;
    if ($d > 0)  return ['+' . $d . ' vs last month', 'kpi-up'];
    if ($d < 0)  return [$d . ' vs last month', 'kpi-down'];
    return ['Same as last month', 'kpi-flat'];
}
[$openDelta, $openClass]  = kpi_delta($openCases, $openedLastMonth);
[$taskDelta, $taskClass]  = kpi_delta($tasksThisMonth, $tasksLastMonth);
?>
<div class="page-head">
  <div>
    <span class="eyebrow-gold">Good to see you</span>
    <h1>Welcome back, <?= htmlspecialchars(explode(' ', $currentUser['full_name'] ?? 'there')[0]) ?>.</h1>
    <p class="sub"><?= date('l, j F Y') ?></p>
  </div>
  <a href="<?= url('cases') ?>" class="btn btn-primary"><?= icon('plus') ?> New matter</a>
</div>

<div class="kpi-grid">
  <a href="<?= url('cases?status=open') ?>" class="card kpi-card">
    <span class="kpi-icon icon-tint-blue"><?= icon('briefcase') ?></span>
    <div class="kpi-value"><?= $openCases ?></div>
    <div class="kpi-label">Open matters</div>
    <span class="kpi-delta <?= $openClass ?>"><?= $openDelta ?></span>
  </a>
  <a href="<?= url('cases?status=pending') ?>" class="card kpi-card">
    <span class="kpi-icon icon-tint-brass"><?= icon('clock') ?></span>
    <div class="kpi-value"><?= $pendingCases ?></div>
    <div class="kpi-label">Pending matters</div>
    <span class="kpi-delta kpi-flat">Awaiting next step</span>
  </a>
  <a href="<?= url('cases?status=closed') ?>" class="card kpi-card">
    <span class="kpi-icon icon-tint-green"><?= icon('check') ?></span>
    <div class="kpi-value"><?= $closedCases ?></div>
    <div class="kpi-label">Resolved matters</div>
    <span class="kpi-delta kpi-flat">Year to date</span>
  </a>
  <a href="<?= url('tasks?status=open') ?>" class="card kpi-card">
    <span class="kpi-icon icon-tint-red"><?= icon('flag') ?></span>
    <div class="kpi-value"><?= $openTasks ?></div>
    <div class="kpi-label">Open tasks</div>
    <span class="kpi-delta <?= $dueSoon > 0 ? 'kpi-down' : 'kpi-flat' ?>"><?= $dueSoon ?> due in 7 days</span>
  </a>
</div>

<div class="grid-2">
  <div class="card card-pad">
    <div class="card-head">
      <h3>Recent matters</h3>
      <a class="link" href="<?= url('cases') ?>">View all →</a>
    </div>
    <?php if ($recentCases): ?>
    <table class="table">
      <thead><tr><th>Matter</th><th>Client</th><th>Status</th><th>Due</th></tr></thead>
      <tbody>
        <?php foreach ($recentCases as $c): ?>
        <tr onclick="window.location='<?= url('cases/' . $c['id']) ?>'" style="cursor:pointer">
          <td>
            <div class="case-title"><?= htmlspecialchars($c['title']) ?></div>
            <div class="case-number"><?= htmlspecialchars($c['case_number']) ?></div>
          </td>
          <td class="case-client"><?= htmlspecialchars($c['client_name']) ?></td>
          <td><span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
          <td class="case-client"><?= fmt_date($c['due_on']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php else: ?>
      <div class="empty-state"><?= icon('briefcase') ?><p>No matters yet.</p></div>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card card-pad">
      <div class="card-head"><h3>Upcoming tasks</h3><a class="link" href="<?= url('tasks') ?>">View all →</a></div>
      <?php if ($upcomingTasks): foreach ($upcomingTasks as $t): ?>
        <a href="<?= $t['case_id'] ? url('cases/' . $t['case_id']) : url('tasks') ?>" class="task-row <?= $t['status'] === 'done' ? 'done' : '' ?>" style="text-decoration:none;color:inherit">
          <span class="task-check"><?= $t['status'] === 'done' ? icon('check') : '' ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="task-meta"><?= htmlspecialchars($t['case_title'] ?? 'No matter') ?> · <?= fmt_date($t['due_on'], 'd M') ?></div>
          </div>
          <span class="badge badge-<?= $t['priority'] ?>" style="margin-left:auto"><?= $t['priority'] ?></span>
        </a>
      <?php endforeach; else: ?>
        <div class="empty-state"><p>No tasks right now.</p></div>
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
          <div class="empty-state"><p>No activity yet.</p></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

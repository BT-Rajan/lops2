<?php
$prevLink = url('calendar?y=' . ($month===1 ? $year-1 : $year) . '&m=' . ($month===1 ? 12 : $month-1) . (is_admin($currentUser) ? '&user='.$viewUserId : ''));
$nextLink = url('calendar?y=' . ($month===12 ? $year+1 : $year) . '&m=' . ($month===12 ? 1 : $month+1) . (is_admin($currentUser) ? '&user='.$viewUserId : ''));
$firstWD  = (int)date('w', strtotime($monthStart));
$daysInM  = (int)date('t', strtotime($monthStart));
$today    = date('Y-m-d');
$viewName = '';
if (is_admin($currentUser) && $viewUserId !== (int)$currentUser['uid']) {
    foreach ($team as $t) { if ((int)$t['id'] === $viewUserId) { $viewName = $t['full_name']; break; } }
}
?>
<div class="page-head">
  <div>
    <span class="eyebrow-gold">Schedule</span>
    <h1><?= date('F Y', strtotime($monthStart)) ?><?= $viewName ? ' — ' . htmlspecialchars($viewName) : '' ?></h1>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <?php if (is_admin($currentUser)): ?>
    <select class="input" style="max-width:200px" onchange="window.location='<?= url('calendar?y=' . $year . '&m=' . $month . '&user=') ?>'+this.value">
      <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>" <?= $viewUserId===(int)$t['id']?'selected':'' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= $prevLink ?>">← Prev</a>
    <a class="btn btn-ghost btn-sm" href="<?= url('calendar?y=' . date('Y') . '&m=' . date('n') . (is_admin($currentUser) ? '&user='.$viewUserId : '')) ?>">Today</a>
    <a class="btn btn-ghost btn-sm" href="<?= $nextLink ?>">Next →</a>
  </div>
</div>

<?php if ($viewUserId === (int)$currentUser['uid']): ?>
<div class="card card-pad" style="margin-bottom:20px">
  <div class="card-head"><h3>Calendar sync</h3></div>
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <?php foreach (['google' => 'Google Calendar', 'microsoft' => 'Microsoft Outlook'] as $provider => $label):
      $ok      = $provider === 'google' ? $googleOk : $microsoftOk;
      $account = $accounts[$provider] ?? null;
      $tint    = $provider === 'google' ? 'icon-tint-blue' : 'icon-tint-brass';
    ?>
    <div style="flex:1;min-width:240px;padding:14px;border:1px solid var(--border-card);border-radius:var(--radius-md)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <span class="<?= $tint ?>" style="width:32px;height:32px;border-radius:8px;display:grid;place-items:center"><?= icon('calendar') ?></span>
        <strong><?= $label ?></strong>
      </div>
      <?php if (!$ok): ?>
        <p class="case-client">Not configured — an admin needs to add credentials in <a href="<?= url('settings') ?>" style="color:var(--accent-600)">Firm settings</a>.</p>
      <?php elseif ($account): ?>
        <p class="case-client">Connected. Last synced: <?= $account['last_synced_at'] ? time_ago($account['last_synced_at']) : 'never' ?>.</p>
        <div style="display:flex;gap:8px;margin-top:10px">
          <form method="post" action="<?= url('calendar/sync') ?>"><?= csrf_field() ?><input type="hidden" name="provider" value="<?= $provider ?>"><button class="btn btn-sm btn-primary" type="submit">Sync now</button></form>
          <form method="post" action="<?= url('calendar/disconnect') ?>" onsubmit="return confirm('Disconnect <?= $label ?>?')"><?= csrf_field() ?><input type="hidden" name="provider" value="<?= $provider ?>"><button class="btn btn-sm btn-ghost" type="submit">Disconnect</button></form>
        </div>
      <?php else: ?>
        <p class="case-client">Two-way sync — tasks push as events, events import as tasks.</p>
        <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= url('calendar/connect?provider=' . $provider) ?>">Connect <?= $label ?></a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card card-pad">
  <div class="cal-grid cal-grid-head">
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?><div><?= $d ?></div><?php endforeach; ?>
  </div>
  <div class="cal-grid">
    <?php for ($i = 0; $i < $firstWD; $i++): ?>
      <div class="cal-cell cal-cell-empty"></div>
    <?php endfor; ?>
    <?php for ($d = 1; $d <= $daysInM; $d++):
      $ds = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $dayTasks = $byDay[$ds] ?? [];
    ?>
      <div class="cal-cell <?= $ds === $today ? 'cal-cell-today' : '' ?>">
        <div class="cal-daynum"><?= $d ?></div>
        <?php foreach (array_slice($dayTasks, 0, 3) as $t): ?>
          <a class="cal-event cal-event-<?= htmlspecialchars($t['priority']) ?> <?= $t['status']==='done'?'cal-event-done':'' ?>"
             href="<?= url('tasks') ?>" title="<?= htmlspecialchars($t['title']) ?>">
            <?= $t['due_time'] ? date('g:ia', strtotime($t['due_time'])) . ' ' : '' ?><?= htmlspecialchars(mb_strimwidth($t['title'], 0, 22, '…')) ?>
          </a>
        <?php endforeach; ?>
        <?php if (count($dayTasks) > 3): ?><div class="cal-event-more">+<?= count($dayTasks)-3 ?> more</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

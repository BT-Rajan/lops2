<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/calendar_sync.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];
$isAdmin = is_admin($current_user);

$page_title = 'Calendar';
$active_nav = 'calendar';

$team = $isAdmin
    ? $pdo->query("SELECT id, full_name, email FROM phpauth_users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC)
    : [['id' => $uid, 'full_name' => $current_user['full_name'] ?? $current_user['email']]];

$viewUserId = $uid;
if ($isAdmin && isset($_GET['user']) && (int)$_GET['user'] > 0) {
    $viewUserId = (int)$_GET['user'];
}

// Month being viewed (defaults to current month)
$year = (int)($_GET['y'] ?? date('Y'));
$month = (int)($_GET['m'] ?? date('n'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$monthStart = sprintf('%04d-%02d-01', $year, $month);
$monthEnd = date('Y-m-t', strtotime($monthStart));
$prevLink = '?y=' . ($month === 1 ? $year - 1 : $year) . '&m=' . ($month === 1 ? 12 : $month - 1) . ($isAdmin ? '&user=' . $viewUserId : '');
$nextLink = '?y=' . ($month === 12 ? $year + 1 : $year) . '&m=' . ($month === 12 ? 1 : $month + 1) . ($isAdmin ? '&user=' . $viewUserId : '');

// Strict per-user visibility: a member can only ever load their own
// tasks here, even if they tamper with ?user= in the URL — re-enforced
// by simply ignoring $_GET['user'] above for non-admins.
$stmt = $pdo->prepare(
    "SELECT t.*, c.case_number, c.title AS case_title FROM legalops_tasks t
     LEFT JOIN legalops_cases c ON c.id = t.case_id
     WHERE t.assigned_to = ? AND t.due_on BETWEEN ? AND ?
     ORDER BY t.due_time IS NULL, t.due_time"
);
$stmt->execute([$viewUserId, $monthStart, $monthEnd]);
$monthTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$byDay = [];
foreach ($monthTasks as $t) {
    $byDay[$t['due_on']][] = $t;
}

// Connected calendar accounts for the CURRENT user only (never another user's).
$stmt = $pdo->prepare('SELECT * FROM legalops_calendar_accounts WHERE uid = ?');
$stmt->execute([$uid]);
$accountsByProvider = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $a) {
    $accountsByProvider[$a['provider']] = $a;
}

$googleConfigured = get_setting($pdo, 'google_client_id') !== '';
$microsoftConfigured = get_setting($pdo, 'microsoft_client_id') !== '';

require __DIR__ . '/includes/app_header.php';

$firstWeekday = (int)date('w', strtotime($monthStart)); // 0=Sun
$daysInMonth = (int)date('t', strtotime($monthStart));
$today = date('Y-m-d');
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Schedule</span>
    <h1>Calendar</h1>
    <p class="sub"><?= date('F Y', strtotime($monthStart)) ?><?= $isAdmin && $viewUserId !== $uid ? ' · viewing ' . htmlspecialchars($team[array_search($viewUserId, array_column($team, 'id'))]['full_name'] ?? '') : '' ?></p>
  </div>
  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <?php if ($isAdmin): ?>
    <select class="input" style="max-width:200px" onchange="window.location='?y=<?= $year ?>&m=<?= $month ?>&user='+this.value">
      <?php foreach ($team as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= $viewUserId === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= $prevLink ?>">← Prev</a>
    <a class="btn btn-ghost btn-sm" href="?y=<?= date('Y') ?>&m=<?= date('n') ?><?= $isAdmin ? '&user=' . $viewUserId : '' ?>">Today</a>
    <a class="btn btn-ghost btn-sm" href="<?= $nextLink ?>">Next →</a>
  </div>
</div>

<?php if ($viewUserId === $uid): ?>
<!-- Calendar sync panel — only ever shown/usable for the logged-in user's own accounts -->
<div class="card card-pad" style="margin-bottom:20px">
  <div class="card-head"><h3>Calendar sync</h3></div>
  <div style="display:flex;gap:16px;flex-wrap:wrap">

    <div style="flex:1;min-width:240px;padding:14px;border:1px solid var(--border-card);border-radius:var(--radius-md)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <span class="icon-tint-blue" style="width:32px;height:32px;border-radius:8px;display:grid;place-items:center"><?= icon('calendar') ?></span>
        <strong>Google Calendar</strong>
      </div>
      <?php if (!$googleConfigured): ?>
        <p class="case-client">Not configured yet — an admin needs to add Google OAuth credentials in <a href="<?= base_url('settings.php') ?>" style="color:var(--accent-600)">Firm settings</a>.</p>
      <?php elseif (isset($accountsByProvider['google'])): ?>
        <p class="case-client">Connected. Last synced: <?= $accountsByProvider['google']['last_synced_at'] ? time_ago($accountsByProvider['google']['last_synced_at']) : 'never' ?>.</p>
        <div style="display:flex;gap:8px;margin-top:10px">
          <form method="post" action="<?= base_url('calendar-sync.php') ?>"><?= csrf_field() ?><input type="hidden" name="provider" value="google"><button class="btn btn-sm btn-primary" type="submit">Sync now</button></form>
          <form method="post" action="<?= base_url('calendar-disconnect.php') ?>" onsubmit="return confirm('Disconnect Google Calendar?')"><?= csrf_field() ?><input type="hidden" name="provider" value="google"><button class="btn btn-sm btn-ghost" type="submit">Disconnect</button></form>
        </div>
      <?php else: ?>
        <p class="case-client">Two-way sync: tasks with a due date push to Google, and events you add there import back as tasks.</p>
        <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= base_url('calendar-oauth.php?provider=google') ?>">Connect Google</a>
      <?php endif; ?>
    </div>

    <div style="flex:1;min-width:240px;padding:14px;border:1px solid var(--border-card);border-radius:var(--radius-md)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
        <span class="icon-tint-brass" style="width:32px;height:32px;border-radius:8px;display:grid;place-items:center"><?= icon('calendar') ?></span>
        <strong>Microsoft Outlook Calendar</strong>
      </div>
      <?php if (!$microsoftConfigured): ?>
        <p class="case-client">Not configured yet — an admin needs to add Microsoft OAuth credentials in <a href="<?= base_url('settings.php') ?>" style="color:var(--accent-600)">Firm settings</a>.</p>
      <?php elseif (isset($accountsByProvider['microsoft'])): ?>
        <p class="case-client">Connected. Last synced: <?= $accountsByProvider['microsoft']['last_synced_at'] ? time_ago($accountsByProvider['microsoft']['last_synced_at']) : 'never' ?>.</p>
        <div style="display:flex;gap:8px;margin-top:10px">
          <form method="post" action="<?= base_url('calendar-sync.php') ?>"><?= csrf_field() ?><input type="hidden" name="provider" value="microsoft"><button class="btn btn-sm btn-primary" type="submit">Sync now</button></form>
          <form method="post" action="<?= base_url('calendar-disconnect.php') ?>" onsubmit="return confirm('Disconnect Microsoft Calendar?')"><?= csrf_field() ?><input type="hidden" name="provider" value="microsoft"><button class="btn btn-sm btn-ghost" type="submit">Disconnect</button></form>
        </div>
      <?php else: ?>
        <p class="case-client">Two-way sync: tasks with a due date push to Outlook, and events you add there import back as tasks.</p>
        <a class="btn btn-sm btn-primary" style="margin-top:10px" href="<?= base_url('calendar-oauth.php?provider=microsoft') ?>">Connect Microsoft</a>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php endif; ?>

<div class="card card-pad">
  <div class="cal-grid cal-grid-head">
    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
  </div>
  <div class="cal-grid">
    <?php for ($i = 0; $i < $firstWeekday; $i++): ?>
      <div class="cal-cell cal-cell-empty"></div>
    <?php endfor; ?>

    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $dayTasks = $byDay[$dateStr] ?? [];
    ?>
      <div class="cal-cell <?= $dateStr === $today ? 'cal-cell-today' : '' ?>">
        <div class="cal-daynum"><?= $d ?></div>
        <?php foreach (array_slice($dayTasks, 0, 3) as $t): ?>
          <div class="cal-event cal-event-<?= htmlspecialchars($t['priority']) ?> <?= $t['status'] === 'done' ? 'cal-event-done' : '' ?>" title="<?= htmlspecialchars($t['title']) ?>">
            <?= $t['due_time'] ? date('g:ia', strtotime($t['due_time'])) . ' ' : '' ?><?= htmlspecialchars(mb_strimwidth($t['title'], 0, 22, '…')) ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($dayTasks) > 3): ?>
          <div class="cal-event-more">+<?= count($dayTasks) - 3 ?> more</div>
        <?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

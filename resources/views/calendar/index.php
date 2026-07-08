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
      $isPast = $ds < $today;
      $canEdit = $viewUserId === (int)$currentUser['uid']; // only editable on your own calendar, not a teammate's
    ?>
      <div class="cal-cell cal-cell-clickable <?= $ds === $today ? 'cal-cell-today' : '' ?>"
           data-date="<?= $ds ?>" data-past="<?= $isPast ? '1' : '0' ?>" data-can-edit="<?= $canEdit ? '1' : '0' ?>"
           data-tasks='<?= htmlspecialchars(json_encode(array_map(fn($t) => [
               'id' => $t['id'], 'title' => $t['title'], 'notes' => $t['notes'], 'priority' => $t['priority'],
               'status' => $t['status'], 'due_time' => $t['due_time'], 'case_id' => $t['case_id'],
               'case_label' => $t['case_number'] ? $t['case_number'] . ' — ' . $t['case_title'] : '',
           ], $dayTasks)), ENT_QUOTES) ?>'>
        <div class="cal-daynum"><?= $d ?></div>
        <?php foreach (array_slice($dayTasks, 0, 3) as $t): ?>
          <div class="cal-event cal-event-<?= htmlspecialchars($t['priority']) ?> <?= $t['status']==='done'?'cal-event-done':'' ?>" title="<?= htmlspecialchars($t['title']) ?>">
            <?= $t['due_time'] ? date('g:ia', strtotime($t['due_time'])) . ' ' : '' ?><?= htmlspecialchars(mb_strimwidth($t['title'], 0, 22, '…')) ?>
          </div>
        <?php endforeach; ?>
        <?php if (count($dayTasks) > 3): ?><div class="cal-event-more">+<?= count($dayTasks)-3 ?> more</div><?php endif; ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<!-- Day modal: view/add/edit/delete tasks for one day -->
<div class="modal-overlay" id="day-modal-overlay">
<div class="card inline-panel" id="day-modal" style="max-width:520px">
  <div class="card-head" style="padding:20px 24px 0">
    <h3 id="day-modal-title">&nbsp;</h3>
    <span class="modal-close" id="day-modal-close"><?= icon('close') ?></span>
  </div>
  <div class="card-pad" style="padding-top:14px">

    <div id="day-modal-tasklist"></div>

    <form method="post" id="day-task-form" style="display:none">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" value="save">
      <input type="hidden" name="due_on" id="day-task-due_on">
      <input type="hidden" name="_redirect_to" value="calendar">
      <input type="hidden" name="_redirect_y" value="<?= $year ?>">
      <input type="hidden" name="_redirect_m" value="<?= $month ?>">
      <input type="hidden" name="_redirect_user" value="<?= $viewUserId ?>">

      <div class="field"><label>Title</label><input class="input" type="text" name="title" id="day-task-title" required></div>
      <div class="input-row">
        <div class="field">
          <label>Related matter (optional)</label>
          <select class="input" name="case_id" id="day-task-case_id">
            <option value="">No matter linked</option>
            <?php foreach ($cases as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['case_number'] . ' — ' . $c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="day-task-priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Time (optional)</label><input class="input" type="time" name="due_time" id="day-task-due_time"></div>
        <?php if (is_admin($currentUser)): ?>
        <div class="field">
          <label>Assign to</label>
          <select class="input" name="assigned_to" id="day-task-assigned_to">
            <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="field"><label>Notes (optional)</label><textarea class="input" name="notes" id="day-task-notes" rows="2"></textarea></div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="day-task-cancel-edit" style="display:none">Cancel</button>
        <button type="submit" class="btn btn-primary" id="day-task-submit">Add task</button>
      </div>
    </form>

    <button type="button" class="btn btn-ghost btn-sm" id="day-task-add-toggle" style="margin-top:10px"><?= icon('plus') ?> Add a task for this day</button>
  </div>
</div>
</div>

<script>
(function () {
  var overlay   = document.getElementById('day-modal-overlay');
  var titleEl   = document.getElementById('day-modal-title');
  var listEl    = document.getElementById('day-modal-tasklist');
  var form      = document.getElementById('day-task-form');
  var addToggle = document.getElementById('day-task-add-toggle');
  var cancelBtn = document.getElementById('day-task-cancel-edit');
  var submitBtn = document.getElementById('day-task-submit');
  var ICON_EDIT  = <?= json_encode(icon('edit')) ?>;
  var ICON_TRASH = <?= json_encode(icon('trash')) ?>;
  var CSRF_HTML  = <?= json_encode(csrf_field()) ?>;
  var TASKS_URL  = <?= json_encode(url('tasks')) ?>;

  function fmtDate(ds) {
    var d = new Date(ds + 'T00:00:00');
    return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  }

  function resetFormToAdd(ds) {
    form.reset();
    form.action = TASKS_URL;
    document.getElementById('day-task-due_on').value = ds;
    submitBtn.textContent = 'Add task';
    cancelBtn.style.display = 'none';
  }

  function startEdit(t, ds) {
    form.action = TASKS_URL + '/' + t.id;
    document.getElementById('day-task-due_on').value = ds;
    document.getElementById('day-task-title').value = t.title || '';
    document.getElementById('day-task-priority').value = t.priority || 'medium';
    document.getElementById('day-task-due_time').value = t.due_time ? t.due_time.slice(0, 5) : '';
    document.getElementById('day-task-notes').value = t.notes || '';
    var caseSel = document.getElementById('day-task-case_id');
    if (caseSel) caseSel.value = t.case_id || '';
    submitBtn.textContent = 'Save changes';
    cancelBtn.style.display = '';
    form.style.display = '';
    form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function renderTaskRow(t, ds, canMutate) {
    var row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border-card)';

    var dot = document.createElement('span');
    dot.className = 'badge badge-' + t.priority;
    dot.style.cssText = 'width:8px;height:8px;padding:0;border-radius:50%;flex-shrink:0';
    row.appendChild(dot);

    var body = document.createElement('div');
    body.style.cssText = 'flex:1;min-width:0';
    var titleDiv = document.createElement('div');
    titleDiv.style.fontWeight = '600';
    if (t.status === 'done') { titleDiv.style.textDecoration = 'line-through'; titleDiv.style.opacity = '.6'; }
    titleDiv.textContent = (t.due_time ? t.due_time.slice(0, 5) + ' — ' : '') + t.title;
    body.appendChild(titleDiv);
    if (t.case_label) {
      var caseDiv = document.createElement('div');
      caseDiv.className = 'case-client';
      caseDiv.textContent = t.case_label;
      body.appendChild(caseDiv);
    }
    row.appendChild(body);

    if (canMutate) {
      var editBtn = document.createElement('button');
      editBtn.type = 'button'; editBtn.className = 'icon-btn btn-sm'; editBtn.style.display = 'inline-grid';
      editBtn.innerHTML = ICON_EDIT;
      editBtn.addEventListener('click', function () { startEdit(t, ds); });
      row.appendChild(editBtn);

      var delForm = document.createElement('form');
      delForm.method = 'post'; delForm.action = TASKS_URL + '/' + t.id + '/delete'; delForm.style.display = 'inline';
      delForm.innerHTML = CSRF_HTML +
        '<input type="hidden" name="_redirect_to" value="calendar">' +
        '<input type="hidden" name="_redirect_y" value="<?= $year ?>">' +
        '<input type="hidden" name="_redirect_m" value="<?= $month ?>">' +
        '<input type="hidden" name="_redirect_user" value="<?= $viewUserId ?>">' +
        '<button class="icon-btn btn-sm" type="submit" style="display:inline-grid;color:var(--danger)">' + ICON_TRASH + '</button>';
      delForm.addEventListener('submit', function (e) { if (!confirm('Delete "' + t.title + '"?')) e.preventDefault(); });
      row.appendChild(delForm);
    }
    return row;
  }

  function openDayModal(cell) {
    var ds = cell.dataset.date;
    var tasks = JSON.parse(cell.dataset.tasks || '[]');
    var canMutate = cell.dataset.canEdit === '1' && cell.dataset.past === '0';

    titleEl.textContent = fmtDate(ds);
    listEl.innerHTML = '';
    if (!tasks.length) {
      var empty = document.createElement('p');
      empty.className = 'case-client';
      empty.textContent = 'No tasks for this day yet.';
      listEl.appendChild(empty);
    } else {
      tasks.forEach(function (t) { listEl.appendChild(renderTaskRow(t, ds, canMutate)); });
    }

    if (canMutate) {
      addToggle.style.display = '';
      form.style.display = 'none';
      resetFormToAdd(ds);
      addToggle.onclick = function () { form.style.display = ''; addToggle.style.display = 'none'; document.getElementById('day-task-title').focus(); };
    } else {
      addToggle.style.display = 'none';
      form.style.display = 'none';
    }

    overlay.classList.add('open');
  }

  document.querySelectorAll('.cal-cell-clickable').forEach(function (cell) {
    cell.addEventListener('click', function () { openDayModal(cell); });
  });
  document.getElementById('day-modal-close').addEventListener('click', function () { overlay.classList.remove('open'); });
  cancelBtn.addEventListener('click', function () {
    var ds = document.getElementById('day-task-due_on').value;
    resetFormToAdd(ds);
    form.style.display = 'none';
    addToggle.style.display = '';
  });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.classList.remove('open'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') overlay.classList.remove('open'); });
})();
</script>

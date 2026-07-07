<div class="page-head">
  <div>
    <span class="eyebrow-gold">Firm-wide board</span>
    <h1>Tasks</h1>
    <p class="sub"><?= count($tasks) ?> task<?= count($tasks) !== 1 ? 's' : '' ?> <?= is_admin($currentUser) ? 'across the team' : 'assigned to you' ?>.</p>
  </div>
  <button class="btn btn-primary" type="button" id="task-toggle-btn"><?= icon('plus') ?> New task</button>
</div>

<!-- Inline add/edit panel -->
<div class="card inline-panel" id="task-panel">
  <form method="post" action="<?= url('tasks') ?>" id="task-form">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="task-panel-title">New task</h3>
      <span class="modal-close" id="task-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="_action" id="task-action" value="save">
      <div class="field"><label>Title</label><input class="input" type="text" name="title" id="f-title" placeholder="What needs doing?" required></div>
      <div class="field"><label>Notes</label><input class="input" type="text" name="notes" id="f-notes" placeholder="Optional detail"></div>
      <div class="input-row">
        <div class="field">
          <label>Linked matter</label>
          <select class="input" name="case_id" id="f-case_id">
            <option value="">No matter linked</option>
            <?php foreach ($cases as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['case_number'] . ' — ' . $c['title']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="f-priority">
            <option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option>
          </select>
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Due date</label><input class="input" type="date" name="due_on" id="f-due_on"></div>
        <div class="field"><label>Due time <span style="font-weight:400;color:var(--text-muted)">(optional)</span></label><input class="input" type="time" name="due_time" id="f-due_time"></div>
      </div>
      <div class="field">
        <label>Assign to<?= is_admin($currentUser) ? '' : ' (you)' ?></label>
        <select class="input" name="assigned_to" id="f-assigned_to" <?= !is_admin($currentUser) ? 'disabled' : '' ?>>
          <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id'] == $currentUser['uid'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option><?php endforeach; ?>
        </select>
        <?php if (!is_admin($currentUser)): ?><input type="hidden" name="assigned_to" value="<?= $currentUser['uid'] ?>"><?php endif; ?>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="task-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save task</button>
      </div>
    </div>
  </form>
</div>

<!-- Search/filter toolbar -->
<form method="get" action="<?= url('tasks') ?>" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search tasks, notes, matter…" value="<?= htmlspecialchars($search) ?>">
  <?php if (is_admin($currentUser)): ?>
  <select class="input" name="assignee" onchange="this.form.submit()">
    <option value="0">Everyone</option>
    <?php foreach ($team as $t): ?><option value="<?= $t['id'] ?>" <?= $assigneeFilter === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option><?php endforeach; ?>
  </select>
  <?php endif; ?>
  <?php foreach (['all' => 'All', 'open' => 'Open', 'pending' => 'Pending', 'in_progress' => 'In progress', 'hold' => 'On hold', 'done' => 'Done'] as $val => $label): ?>
    <a class="filter-chip <?= $statusFilter === $val ? 'active' : '' ?>" href="<?= url('tasks?status=' . $val) ?>"><?= $label ?></a>
  <?php endforeach; ?>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($tasks): ?>
  <table class="table">
    <thead><tr><th>Task</th><th>Matter</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($tasks as $t):
        $uid = $currentUser['uid'];
        $canEdit = is_admin($currentUser) || $t['assigned_to'] == $uid || $t['created_by'] == $uid;
      ?>
      <tr>
        <td>
          <div class="case-title"><?= htmlspecialchars($t['title']) ?></div>
          <?php if ($t['notes']): ?><div class="case-client"><?= htmlspecialchars($t['notes']) ?></div><?php endif; ?>
          <?php if ($t['source'] === 'hearing_cron'): ?><span class="badge badge-pending" style="margin-top:4px"><?= icon('flag') ?> Hearing</span><?php endif; ?>
          <?php if ($t['status'] === 'hold' && $t['hold_reason']): ?><div class="case-client" style="color:var(--danger)">On hold: <?= htmlspecialchars($t['hold_reason']) ?></div><?php endif; ?>
        </td>
        <td class="case-client"><?= $t['case_number'] ? htmlspecialchars($t['case_number']) : '—' ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <span class="avatar avatar-sm" style="background:<?= htmlspecialchars($t['assignee_color'] ?: '#3B6FE0') ?>"><?= htmlspecialchars(initials($t['assignee_name'] ?: '?')) ?></span>
            <span class="case-client"><?= htmlspecialchars($t['assignee_name'] ?: 'Unassigned') ?></span>
          </div>
        </td>
        <td><span class="badge badge-<?= $t['priority'] ?>"><?= $t['priority'] ?></span></td>
        <td class="case-client"><?= $t['due_on'] ? date('d M Y', strtotime($t['due_on'])) . ($t['due_time'] ? ', ' . date('g:i A', strtotime($t['due_time'])) : '') : '—' ?></td>
        <td><span class="badge badge-<?= $t['status'] ?>"><?= str_replace('_', ' ', $t['status']) ?></span></td>
        <td style="text-align:right;white-space:nowrap">
          <?php if ($canEdit): ?>
            <?php if ($t['status'] !== 'done'): ?>
            <form method="post" action="<?= url('tasks/' . $t['id']) ?>" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="_action" value="set_status"><input type="hidden" name="status" value="done">
              <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--success)" title="Mark done"><?= icon('check') ?></button>
            </form>
            <button class="icon-btn btn-sm task-hold-btn" style="display:inline-grid" data-id="<?= $t['id'] ?>" title="Put on hold"><?= icon('clock') ?></button>
            <?php endif; ?>
            <button class="icon-btn btn-sm task-edit-btn" style="display:inline-grid" type="button"
              data-task='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
            <form method="post" action="<?= url('tasks/' . $t['id'] . '/delete') ?>" style="display:inline" onsubmit="return confirm('Delete this task?')">
              <?= csrf_field() ?>
              <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('tasks') ?><p>No tasks match that search.</p></div>
  <?php endif; ?>
</div>

<!-- Hold modal -->
<div class="modal-overlay" id="hold-modal">
  <div class="modal-box" style="max-width:420px">
    <form method="post" id="hold-form">
      <div class="modal-head"><h3>Put on hold</h3><span class="modal-close" data-close-modal><?= icon('close') ?></span></div>
      <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="set_hold">
        <div class="field"><label>Reason (optional)</label><input class="input" type="text" name="hold_reason" placeholder="e.g. Awaiting client documents"></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-primary">Put on hold</button>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var panel     = document.getElementById('task-panel');
  var taskForm  = document.getElementById('task-form');
  var holdModal = document.getElementById('hold-modal');
  var holdForm  = document.getElementById('hold-form');

  function openPanel(){ panel.classList.add('open'); panel.scrollIntoView({behavior:'smooth',block:'start'}); }
  function closePanel(){ panel.classList.remove('open'); }

  document.getElementById('task-toggle-btn').addEventListener('click',function(){
    if(panel.classList.contains('open') && !document.getElementById('f-title').value){ closePanel(); } else { taskForm.action='<?= url('tasks') ?>'; document.getElementById('task-panel-title').textContent='New task'; taskForm.reset(); openPanel(); document.getElementById('f-title').focus(); }
  });
  document.getElementById('task-cancel-btn').addEventListener('click', closePanel);
  document.getElementById('task-panel-close').addEventListener('click', closePanel);

  document.querySelectorAll('.task-edit-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      var t = JSON.parse(btn.getAttribute('data-task'));
      taskForm.action = '<?= url('tasks/') ?>' + t.id;
      document.getElementById('task-panel-title').textContent = 'Edit task';
      ['title','notes','priority','due_on','due_time'].forEach(function(f){ var el = document.getElementById('f-'+f); if(el) el.value = t[f]||''; });
      var ci = document.getElementById('f-case_id'); if(ci) ci.value = t.case_id||'';
      var ai = document.getElementById('f-assigned_to'); if(ai) ai.value = t.assigned_to||'';
      openPanel();
    });
  });

  document.querySelectorAll('.task-hold-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      holdForm.action = '<?= url('tasks/') ?>' + btn.getAttribute('data-id');
      holdModal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function(btn){
    btn.addEventListener('click',function(){ btn.closest('.modal-overlay').classList.remove('open'); });
  });
  document.querySelectorAll('.modal-overlay').forEach(function(o){
    o.addEventListener('click',function(e){ if(e.target===o) o.classList.remove('open'); });
  });
})();
</script>

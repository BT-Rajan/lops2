<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];
$isAdmin = is_admin($current_user);

$page_title = 'Tasks';
$active_nav = 'tasks';

// Team list for the assignee dropdown. Admins can assign to anyone;
// members only ever see themselves, so they can't even discover who
// else is on the team via this dropdown.
if ($isAdmin) {
    $team = $pdo->query("SELECT id, full_name, email FROM phpauth_users ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $team = [['id' => $uid, 'full_name' => $current_user['full_name'] ?? $current_user['email'], 'email' => $current_user['email']]];
}

$cases = $pdo->query("SELECT id, case_number, title FROM legalops_cases WHERE status != 'closed' ORDER BY case_number")->fetchAll(PDO::FETCH_ASSOC);

// ---- Handle actions ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $caseId = (int)($_POST['case_id'] ?? 0) ?: null;
        $dueOn = $_POST['due_on'] ?: null;
        $dueTime = $_POST['due_time'] ?: null;
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
        $assignedTo = (int)($_POST['assigned_to'] ?? $uid);

        // Hard access-control rule: a non-admin can never assign a task
        // to anyone but themselves, regardless of what the form posts.
        if (!$isAdmin) {
            $assignedTo = $uid;
        }

        if ($title === '') {
            flash('error', 'Task title is required.');
        } else {
            if ($id > 0) {
                // Members may only edit tasks that are theirs (assigned to
                // or created by them) — re-check ownership server-side.
                $check = $pdo->prepare('SELECT assigned_to, created_by FROM legalops_tasks WHERE id = ?');
                $check->execute([$id]);
                $existing = $check->fetch(PDO::FETCH_ASSOC);

                if (!$existing || (!$isAdmin && $existing['assigned_to'] != $uid && $existing['created_by'] != $uid)) {
                    flash('error', "You don't have access to that task.");
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE legalops_tasks SET title=?, notes=?, case_id=?, due_on=?, due_time=?, priority=?, assigned_to=? WHERE id=?'
                    );
                    $stmt->execute([$title, $notes ?: null, $caseId, $dueOn, $dueTime, $priority, $assignedTo, $id]);
                    log_activity($pdo, $uid, 'task_updated', 'Updated task "' . $title . '"');
                    flash('success', 'Task updated.');
                }
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO legalops_tasks (case_id, title, notes, due_on, due_time, priority, status, assigned_to, created_by, source)
                     VALUES (?,?,?,?,?,?,'pending',?,?,'manual')"
                );
                $stmt->execute([$caseId, $title, $notes ?: null, $dueOn, $dueTime, $priority, $assignedTo, $uid]);
                log_activity($pdo, $uid, 'task_created', 'Created task "' . $title . '"');
                flash('success', 'Task created.');
            }
        }
    } elseif (in_array($action, ['set_status', 'set_hold', 'delete'], true)) {
        $id = (int)($_POST['id'] ?? 0);
        $check = $pdo->prepare('SELECT assigned_to, created_by, title FROM legalops_tasks WHERE id = ?');
        $check->execute([$id]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);

        if (!$existing || (!$isAdmin && $existing['assigned_to'] != $uid && $existing['created_by'] != $uid)) {
            flash('error', "You don't have access to that task.");
        } elseif ($action === 'set_status') {
            $status = in_array($_POST['status'] ?? '', ['pending', 'in_progress', 'done'], true) ? $_POST['status'] : 'pending';
            $pdo->prepare("UPDATE legalops_tasks SET status = ?, hold_reason = NULL WHERE id = ?")->execute([$status, $id]);
            flash('success', 'Task marked ' . str_replace('_', ' ', $status) . '.');
        } elseif ($action === 'set_hold') {
            $reason = trim($_POST['hold_reason'] ?? '');
            $pdo->prepare("UPDATE legalops_tasks SET status = 'hold', hold_reason = ? WHERE id = ?")->execute([$reason ?: null, $id]);
            log_activity($pdo, $uid, 'task_hold', 'Put task "' . $existing['title'] . '" on hold' . ($reason ? ': ' . $reason : ''));
            flash('success', 'Task put on hold.');
        } elseif ($action === 'delete') {
            $pdo->prepare('DELETE FROM legalops_tasks WHERE id = ?')->execute([$id]);
            flash('success', 'Task deleted.');
        }
    }

    header('Location: ' . base_url('tasks.php' . (isset($_GET['_redirect']) ? '?' . $_GET['_redirect'] : '')));
    exit;
}

// ---- Listing: members only ever see their own (assigned OR created); ------
// ---- admins can see everyone's, with a filter. -----------------------------
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$assigneeFilter = (int)($_GET['assignee'] ?? 0);

$sql = 'SELECT t.*, c.case_number, c.title AS case_title, u.full_name AS assignee_name, u.avatar_color AS assignee_color
        FROM legalops_tasks t
        LEFT JOIN legalops_cases c ON c.id = t.case_id
        LEFT JOIN phpauth_users u ON u.id = t.assigned_to
        WHERE 1=1';
$params = [];

if (!$isAdmin) {
    $sql .= ' AND (t.assigned_to = ? OR t.created_by = ?)';
    $params[] = $uid;
    $params[] = $uid;
} elseif ($assigneeFilter > 0) {
    $sql .= ' AND t.assigned_to = ?';
    $params[] = $assigneeFilter;
}

if (in_array($statusFilter, ['pending', 'in_progress', 'hold', 'done'], true)) {
    $sql .= ' AND t.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (t.title LIKE ? OR t.notes LIKE ? OR c.case_number LIKE ? OR c.title LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
$sql .= ' ORDER BY (t.status = "done"), (t.due_on IS NULL), t.due_on ASC, FIELD(t.priority,"high","medium","low")';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Firm-wide board</span>
    <h1>Tasks</h1>
    <p class="sub"><?= count($tasks) ?> task<?= count($tasks) === 1 ? '' : 's' ?> <?= $isAdmin ? 'across the team' : 'assigned to you' ?>.</p>
  </div>
  <button class="btn btn-primary" type="button" id="task-toggle-btn"><?= icon('plus') ?> New task</button>
</div>

<!-- Inline add/edit panel -->
<div class="card inline-panel" id="task-panel">
  <form method="post">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="task-panel-title">New task</h3>
      <span class="modal-close" id="task-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f-id" value="">

      <div class="field">
        <label>Title</label>
        <input class="input" type="text" name="title" id="f-title" placeholder="What needs doing?" required>
      </div>
      <div class="field">
        <label>Notes</label>
        <input class="input" type="text" name="notes" id="f-notes" placeholder="Optional detail">
      </div>

      <div class="input-row">
        <div class="field">
          <label>Linked matter</label>
          <select class="input" name="case_id" id="f-case_id">
            <option value="">No matter linked</option>
            <?php foreach ($cases as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['case_number'] . ' — ' . $c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="f-priority">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>

      <div class="input-row">
        <div class="field"><label>Due date</label><input class="input" type="date" name="due_on" id="f-due_on"></div>
        <div class="field"><label>Due time <span style="color:var(--text-muted);font-weight:400">(optional)</span></label><input class="input" type="time" name="due_time" id="f-due_time"></div>
      </div>

      <div class="field">
        <label>Assign to<?= $isAdmin ? '' : ' (you)' ?></label>
        <select class="input" name="assigned_to" id="f-assigned_to" <?= $isAdmin ? '' : 'disabled' ?>>
          <?php foreach ($team as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $uid ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!$isAdmin): ?><input type="hidden" name="assigned_to" value="<?= $uid ?>"><?php endif; ?>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="task-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save task</button>
      </div>
    </div>
  </form>
</div>

<form method="get" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search tasks, notes or matter…" value="<?= htmlspecialchars($search) ?>">
  <?php if ($isAdmin): ?>
  <select class="input" name="assignee" onchange="this.form.submit()">
    <option value="0">Everyone</option>
    <?php foreach ($team as $t): ?>
      <option value="<?= (int)$t['id'] ?>" <?= $assigneeFilter === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['full_name'] ?: $t['email']) ?></option>
    <?php endforeach; ?>
  </select>
  <?php endif; ?>
  <a class="filter-chip <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all<?= $isAdmin && $assigneeFilter ? '&assignee=' . $assigneeFilter : '' ?>">All</a>
  <a class="filter-chip <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending<?= $isAdmin && $assigneeFilter ? '&assignee=' . $assigneeFilter : '' ?>">Pending</a>
  <a class="filter-chip <?= $statusFilter === 'in_progress' ? 'active' : '' ?>" href="?status=in_progress<?= $isAdmin && $assigneeFilter ? '&assignee=' . $assigneeFilter : '' ?>">In progress</a>
  <a class="filter-chip <?= $statusFilter === 'hold' ? 'active' : '' ?>" href="?status=hold<?= $isAdmin && $assigneeFilter ? '&assignee=' . $assigneeFilter : '' ?>">On hold</a>
  <a class="filter-chip <?= $statusFilter === 'done' ? 'active' : '' ?>" href="?status=done<?= $isAdmin && $assigneeFilter ? '&assignee=' . $assigneeFilter : '' ?>">Done</a>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($tasks): ?>
  <table class="table">
    <thead>
      <tr><th>Task</th><th>Matter</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($tasks as $t):
        $canEdit = $isAdmin || (int)$t['assigned_to'] === $uid || (int)$t['created_by'] === $uid;
      ?>
      <tr>
        <td>
          <div class="case-title"><?= htmlspecialchars($t['title']) ?></div>
          <?php if ($t['notes']): ?><div class="case-client"><?= htmlspecialchars($t['notes']) ?></div><?php endif; ?>
          <?php if ($t['source'] === 'hearing_cron'): ?><span class="badge badge-pending" style="margin-top:4px"><?= icon('flag') ?> Hearing reminder</span><?php endif; ?>
          <?php if ($t['status'] === 'hold' && $t['hold_reason']): ?><div class="case-client" style="color:var(--danger)">On hold: <?= htmlspecialchars($t['hold_reason']) ?></div><?php endif; ?>
        </td>
        <td class="case-client"><?= $t['case_number'] ? htmlspecialchars($t['case_number']) : '—' ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <span class="avatar avatar-sm" style="background:<?= htmlspecialchars($t['assignee_color'] ?: '#3B6FE0') ?>"><?= htmlspecialchars(initials($t['assignee_name'] ?: '?')) ?></span>
            <span class="case-client"><?= htmlspecialchars($t['assignee_name'] ?: 'Unassigned') ?></span>
          </div>
        </td>
        <td><span class="badge badge-<?= htmlspecialchars($t['priority']) ?>"><?= htmlspecialchars($t['priority']) ?></span></td>
        <td class="case-client"><?= $t['due_on'] ? date('d M Y', strtotime($t['due_on'])) . ($t['due_time'] ? ', ' . date('g:i A', strtotime($t['due_time'])) : '') : '—' ?></td>
        <td><span class="badge badge-<?= htmlspecialchars($t['status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $t['status'])) ?></span></td>
        <td style="text-align:right;white-space:nowrap">
          <?php if ($canEdit): ?>
            <?php if ($t['status'] !== 'done'): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?><input type="hidden" name="action" value="set_status">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="status" value="done">
              <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--success)" title="Mark done"><?= icon('check') ?></button>
            </form>
            <?php endif; ?>
            <button type="button" class="icon-btn btn-sm task-hold-btn" style="display:inline-grid" data-id="<?= (int)$t['id'] ?>" title="Put on hold"><?= icon('clock') ?></button>
            <button class="icon-btn btn-sm task-edit-btn" style="display:inline-grid"
              type="button"
              data-task='<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>' title="Edit"><?= icon('edit') ?></button>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this task?')">
              <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" title="Delete"><?= icon('trash') ?></button>
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

<!-- Hold reason mini-panel -->
<div class="modal-overlay" id="hold-modal">
  <div class="modal-box" style="max-width:420px">
    <form method="post">
      <div class="modal-head"><h3>Put task on hold</h3><span class="modal-close" data-close-modal><?= icon('close') ?></span></div>
      <div class="modal-body">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_hold">
        <input type="hidden" name="id" id="hold-task-id" value="">
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
(function () {
  var panel = document.getElementById('task-panel');
  var form = panel.querySelector('form');
  var title = document.getElementById('task-panel-title');

  function openPanel() { panel.classList.add('open'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  function closePanel() { panel.classList.remove('open'); }
  function resetForm() { form.reset(); document.getElementById('f-id').value = ''; title.textContent = 'New task'; }

  document.getElementById('task-toggle-btn').addEventListener('click', function () {
    if (panel.classList.contains('open') && document.getElementById('f-id').value === '') { closePanel(); }
    else { resetForm(); openPanel(); document.getElementById('f-title').focus(); }
  });
  document.getElementById('task-cancel-btn').addEventListener('click', closePanel);
  document.getElementById('task-panel-close').addEventListener('click', closePanel);

  document.querySelectorAll('.task-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var t = JSON.parse(btn.getAttribute('data-task'));
      title.textContent = 'Edit task';
      document.getElementById('f-id').value = t.id;
      document.getElementById('f-title').value = t.title;
      document.getElementById('f-notes').value = t.notes || '';
      document.getElementById('f-case_id').value = t.case_id || '';
      document.getElementById('f-priority').value = t.priority;
      document.getElementById('f-due_on').value = t.due_on || '';
      document.getElementById('f-due_time').value = t.due_time || '';
      var assignSel = document.getElementById('f-assigned_to');
      if (assignSel) assignSel.value = t.assigned_to;
      openPanel();
    });
  });

  document.querySelectorAll('.task-hold-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('hold-task-id').value = btn.getAttribute('data-id');
      document.getElementById('hold-modal').classList.add('open');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function (btn) {
    btn.addEventListener('click', function () { btn.closest('.modal-overlay').classList.remove('open'); });
  });
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/case_types.php';
$current_user = require_login($auth);
$uid = (int)$current_user['uid'];

$caseId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM legalops_cases WHERE id = ?');
$stmt->execute([$caseId]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$case) {
    flash('error', 'That matter could not be found.');
    header('Location: ' . base_url('cases.php'));
    exit;
}

// ---- POST actions -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_core') {
        $caseNumber = trim($_POST['case_number'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $client = trim($_POST['client_name'] ?? '');
        $practiceArea = trim($_POST['practice_area'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['open', 'pending', 'closed'], true) ? $_POST['status'] : $case['status'];
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : $case['priority'];
        $openedOn = $_POST['opened_on'] ?: null;
        $dueOn = $_POST['due_on'] ?: null;

        if ($title === '' || $client === '' || $caseNumber === '') {
            flash('error', 'Matter number, title and client are required.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE legalops_cases SET case_number=?, title=?, client_name=?, practice_area=?, status=?, priority=?, opened_on=?, due_on=? WHERE id=?'
            );
            $stmt->execute([$caseNumber, $title, $client, $practiceArea, $status, $priority, $openedOn, $dueOn, $caseId]);
            log_activity($pdo, $uid, 'case_updated', 'Updated case ' . $caseNumber . ' — ' . $title, ['case_id' => $caseId]);
            flash('success', 'Matter updated.');
        }
    } elseif ($action === 'set_status') {
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['open', 'pending', 'closed'], true)) {
            $pdo->prepare('UPDATE legalops_cases SET status = ? WHERE id = ?')->execute([$status, $caseId]);
            log_activity($pdo, $uid, 'case_status', $case['case_number'] . ' moved to status: ' . $status, ['case_id' => $caseId]);
            flash('success', 'Matter status updated.');
        }
    } elseif ($action === 'add_task') {
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flash('error', 'Task title is required.');
        } else {
            $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
            $stmt = $pdo->prepare(
                'INSERT INTO legalops_tasks (case_id, title, due_on, priority, assigned_to, created_by) VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$caseId, $title, $_POST['due_on'] ?: null, $priority, $uid, $uid]);
            log_activity($pdo, $uid, 'task_created', 'Added task "' . $title . '" to ' . $case['case_number'], ['case_id' => $caseId]);
            flash('success', 'Task added.');
        }
    } elseif ($action === 'toggle_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM legalops_tasks WHERE id = ? AND case_id = ?');
        $stmt->execute([$taskId, $caseId]);
        if ($task = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $next = $task['status'] === 'done' ? 'pending' : 'done';
            $pdo->prepare('UPDATE legalops_tasks SET status = ? WHERE id = ?')->execute([$next, $taskId]);
            if ($next === 'done') {
                log_activity($pdo, $uid, 'task_done', 'Completed task "' . $task['title'] . '" on ' . $case['case_number'], ['case_id' => $caseId]);
            }
        }
    } elseif ($action === 'delete_task') {
        $pdo->prepare('DELETE FROM legalops_tasks WHERE id = ? AND case_id = ?')->execute([(int)($_POST['task_id'] ?? 0), $caseId]);
        flash('success', 'Task removed.');
    } elseif ($action === 'upload_doc') {
        $docType = trim($_POST['doc_type'] ?? 'Other');
        $notes = trim($_POST['notes'] ?? '');
        $result = handle_case_upload($pdo, $caseId, $docType, $_FILES['document'] ?? [], $uid, $notes);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok']) {
            log_activity($pdo, $uid, 'document_uploaded', 'Uploaded ' . $docType . ' to ' . $case['case_number'] . ' — ' . $case['title'], ['case_id' => $caseId]);
        }
    } elseif ($action === 'delete_doc') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM legalops_case_documents WHERE id = ? AND case_id = ?');
        $stmt->execute([$docId, $caseId]);
        if ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $path = rtrim(CASE_UPLOAD_DIR, '/') . '/' . $caseId . '/' . $doc['stored_name'];
            if (is_file($path)) { @unlink($path); }
            $pdo->prepare('DELETE FROM legalops_case_documents WHERE id = ?')->execute([$docId]);
            log_activity($pdo, $uid, 'document_deleted', 'Removed document "' . $doc['original_name'] . '" from ' . $case['case_number'], ['case_id' => $caseId]);
            flash('success', 'Document removed.');
        }
    } elseif ($action === 'delete_case') {
        $dir = rtrim(CASE_UPLOAD_DIR, '/') . '/' . $caseId;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $f) { @unlink($f); }
            @rmdir($dir);
        }
        $pdo->prepare('DELETE FROM legalops_case_documents WHERE case_id = ?')->execute([$caseId]);
        $pdo->prepare('DELETE FROM legalops_tasks WHERE case_id = ?')->execute([$caseId]);
        $pdo->prepare('DELETE FROM legalops_cases WHERE id = ?')->execute([$caseId]);
        log_activity($pdo, $uid, 'case_deleted', 'Removed case ' . $case['case_number'] . ' — ' . $case['title'], ['case_id' => $caseId]);
        flash('success', 'Matter removed.');
        header('Location: ' . base_url('cases.php'));
        exit;
    }

    header('Location: ' . base_url('case-view.php?id=' . $caseId));
    exit;
}

// ---- Re-fetch fresh data for rendering -------------------------------------
$stmt = $pdo->prepare('SELECT * FROM legalops_cases WHERE id = ?');
$stmt->execute([$caseId]);
$case = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM legalops_tasks WHERE case_id = ? ORDER BY (status = "done") ASC, due_on ASC');
$stmt->execute([$caseId]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM legalops_case_documents WHERE case_id = ? ORDER BY uploaded_at DESC');
$stmt->execute([$caseId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT a.*, u.full_name FROM legalops_activity a LEFT JOIN phpauth_users u ON u.id = a.uid WHERE a.case_id = ? ORDER BY a.created_at DESC LIMIT 8');
$stmt->execute([$caseId]);
$activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

$openTaskCount = count(array_filter($tasks, fn($t) => $t['status'] !== 'done'));

$page_title = $case['title'];
$active_nav = 'cases';

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold"><?= htmlspecialchars($case['case_number']) ?></span>
    <h1><?= htmlspecialchars($case['title']) ?></h1>
    <p class="sub">
      <span class="badge badge-<?= htmlspecialchars($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></span>
      &nbsp;<span class="badge badge-<?= htmlspecialchars($case['priority']) ?>"><?= htmlspecialchars($case['priority']) ?> priority</span>
      &nbsp;<?= htmlspecialchars($case['client_name']) ?>
      <?= $case['practice_area'] ? ' · ' . htmlspecialchars($case['practice_area']) : '' ?>
    </p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= base_url('cases.php') ?>">← All matters</a>
    <button class="btn btn-primary" type="button" id="edit-toggle-btn"><?= icon('edit') ?> Edit details</button>
  </div>
</div>

<!-- Edit core details (inline, not a modal) -->
<div class="card inline-panel" id="edit-panel">
  <form method="post">
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Edit matter details</h3>
      <span class="modal-close" id="edit-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_core">
      <div class="input-row">
        <div class="field">
          <label>Matter number</label>
          <input class="input mono" type="text" name="case_number" value="<?= htmlspecialchars($case['case_number']) ?>" required>
        </div>
        <div class="field">
          <label>Practice area</label>
          <input class="input" type="text" name="practice_area" value="<?= htmlspecialchars($case['practice_area'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label>Matter title</label>
        <input class="input" type="text" name="title" value="<?= htmlspecialchars($case['title']) ?>" required>
      </div>
      <div class="field">
        <label>Client name</label>
        <input class="input" type="text" name="client_name" value="<?= htmlspecialchars($case['client_name']) ?>" required>
      </div>
      <div class="input-row">
        <div class="field">
          <label>Status</label>
          <select class="input" name="status">
            <option value="open" <?= $case['status'] === 'open' ? 'selected' : '' ?>>Open</option>
            <option value="pending" <?= $case['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="closed" <?= $case['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority">
            <option value="low" <?= $case['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= $case['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= $case['priority'] === 'high' ? 'selected' : '' ?>>High</option>
          </select>
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Opened on</label><input class="input" type="date" name="opened_on" value="<?= htmlspecialchars($case['opened_on'] ?? '') ?>"></div>
        <div class="field"><label>Due on</label><input class="input" type="date" name="due_on" value="<?= htmlspecialchars($case['due_on'] ?? '') ?>"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-ghost" id="edit-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </form>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Summary -->
    <div class="card card-pad">
      <div class="card-head"><h3>Matter summary</h3></div>
      <table class="table">
        <tr><td style="width:160px;color:var(--text-muted)">Client</td><td><?= htmlspecialchars($case['client_name']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Practice area</td><td><?= htmlspecialchars($case['practice_area'] ?: '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Opened</td><td><?= $case['opened_on'] ? date('d M Y', strtotime($case['opened_on'])) : '—' ?></td></tr>
        <tr><td style="color:var(--text-muted)">Due</td><td><?= $case['due_on'] ? date('d M Y', strtotime($case['due_on'])) : '—' ?></td></tr>
        <tr><td style="color:var(--text-muted)">On file since</td><td><?= date('d M Y', strtotime($case['created_at'])) ?></td></tr>
      </table>
      <form method="post" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_status">
        <?php foreach (['open', 'pending', 'closed'] as $stage): ?>
          <button type="submit" name="status" value="<?= $stage ?>"
            class="filter-chip <?= $case['status'] === $stage ? 'active' : '' ?>">
            <?= htmlspecialchars(ucfirst($stage)) ?>
          </button>
        <?php endforeach; ?>
      </form>
    </div>

    <!-- Tasks tied to this matter -->
    <div class="card card-pad">
      <div class="card-head">
        <h3>Tasks <?= $openTaskCount > 0 ? '(' . $openTaskCount . ' open)' : '' ?></h3>
        <button class="btn btn-sm btn-primary" type="button" id="task-toggle-btn"><?= icon('plus') ?> Add</button>
      </div>

      <div class="inline-panel" id="task-panel">
        <form method="post" style="padding:4px 0 14px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_task">
          <div class="field"><label>Task title</label><input class="input" type="text" name="title" required></div>
          <div class="input-row">
            <div class="field"><label>Due on</label><input class="input" type="date" name="due_on"></div>
            <div class="field">
              <label>Priority</label>
              <select class="input" name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
              </select>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:10px">
            <button type="button" class="btn btn-ghost" id="task-cancel-btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Save task</button>
          </div>
        </form>
      </div>

      <?php if ($tasks): foreach ($tasks as $t): ?>
        <div class="task-row <?= $t['status'] === 'done' ? 'done' : '' ?>">
          <form method="post" style="display:contents">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_task">
            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="task-check" style="cursor:pointer;border:none;background:none"><?= $t['status'] === 'done' ? icon('check') : '' ?></button>
          </form>
          <div>
            <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="task-meta">Due <?= $t['due_on'] ? date('d M', strtotime($t['due_on'])) : '—' ?></div>
          </div>
          <span class="badge badge-<?= htmlspecialchars($t['priority']) ?>" style="margin-left:auto"><?= htmlspecialchars($t['priority']) ?></span>
          <form method="post" style="display:inline" onsubmit="return confirm('Remove this task?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_task">
            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
          </form>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><?= icon('tasks') ?><p>No tasks on this matter yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Danger zone -->
    <div class="card card-pad" style="border-color:rgba(193,59,59,0.25)">
      <div class="card-head"><h3 style="color:var(--danger)">Remove matter</h3></div>
      <p class="case-client" style="margin-bottom:14px">Deletes this matter along with its tasks and uploaded documents. This can't be undone.</p>
      <form method="post" onsubmit="return confirm('Permanently delete this matter and all related records?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_case">
        <button type="submit" class="btn btn-ghost" style="border-color:var(--danger);color:var(--danger)">Delete matter</button>
      </form>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Documents -->
    <div class="card card-pad" id="documents">
      <div class="card-head"><h3>Documents</h3></div>

      <form method="post" enctype="multipart/form-data" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-card)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_doc">
        <div class="input-row">
          <div class="field">
            <label>Document type</label>
            <select class="input" name="doc_type">
              <?php foreach (case_doc_types() as $dt): ?><option><?= htmlspecialchars($dt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Notes <span style="color:var(--text-muted);font-weight:400">(optional)</span></label><input class="input" type="text" name="notes" placeholder="e.g. Final signed copy"></div>
        </div>
        <div class="field">
          <label>File <span style="color:var(--text-muted);font-weight:400">(PDF, JPG, PNG, DOC, DOCX — up to 5MB)</span></label>
          <input class="input" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
      </form>

      <?php if ($documents): foreach ($documents as $d): ?>
        <div class="task-row">
          <span class="task-check" style="border-color:var(--accent-600);color:var(--accent-600)"><?= icon('documents') ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($d['doc_type']) ?></div>
            <div class="task-meta">
              <?= htmlspecialchars($d['original_name']) ?> · <?= round($d['file_size'] / 1024) ?>KB
              <?= $d['notes'] ? ' · ' . htmlspecialchars($d['notes']) : '' ?>
              · <?= time_ago($d['uploaded_at']) ?>
            </div>
          </div>
          <div style="margin-left:auto;display:flex;gap:6px">
            <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= base_url('download.php?doc=' . (int)$d['id'] . '&type=case') ?>" target="_blank" rel="noopener"><?= icon('download') ?></a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this document?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_doc">
              <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
              <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><?= icon('documents') ?><p>No documents uploaded yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Activity on this matter -->
    <div class="card card-pad">
      <div class="card-head"><h3>Activity on this matter</h3></div>
      <div class="activity-feed">
        <?php if ($activity): foreach ($activity as $a): ?>
          <div class="activity-item">
            <span class="activity-dot"></span>
            <div>
              <p><?= htmlspecialchars($a['description']) ?></p>
              <time><?= htmlspecialchars($a['full_name'] ?? 'Someone') ?> · <?= time_ago($a['created_at']) ?></time>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><p>No activity logged on this matter yet.</p></div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  function wireToggle(toggleId, panelId, cancelId, closeId) {
    var toggle = document.getElementById(toggleId);
    var panel = document.getElementById(panelId);
    if (!toggle || !panel) return;
    toggle.addEventListener('click', function () {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    var cancel = document.getElementById(cancelId);
    if (cancel) cancel.addEventListener('click', function () { panel.classList.remove('open'); });
    var close = document.getElementById(closeId);
    if (close) close.addEventListener('click', function () { panel.classList.remove('open'); });
  }
  wireToggle('edit-toggle-btn', 'edit-panel', 'edit-cancel-btn', 'edit-panel-close');
  wireToggle('task-toggle-btn', 'task-panel', 'task-cancel-btn', null);
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);

$page_title = 'Cases';
$active_nav = 'cases';

// ---- Handle form actions (add / edit / delete) --------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $caseNumber = trim($_POST['case_number'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $client = trim($_POST['client_name'] ?? '');
        $practiceArea = trim($_POST['practice_area'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['open', 'pending', 'closed'], true) ? $_POST['status'] : 'open';
        $priority = in_array($_POST['priority'] ?? '', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
        $openedOn = $_POST['opened_on'] ?: null;
        $dueOn = $_POST['due_on'] ?: null;

        if ($title === '' || $client === '' || $caseNumber === '') {
            flash('error', 'Matter number, title and client are required.');
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE legalops_cases SET case_number=?, title=?, client_name=?, practice_area=?, status=?, priority=?, opened_on=?, due_on=? WHERE id=?'
                );
                $stmt->execute([$caseNumber, $title, $client, $practiceArea, $status, $priority, $openedOn, $dueOn, $id]);
                log_activity($pdo, (int)$current_user['uid'], 'case_updated', 'Updated case ' . $caseNumber . ' — ' . $title, ['case_id' => $id]);
                flash('success', 'Matter updated.');
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO legalops_cases (case_number, title, client_name, practice_area, status, priority, opened_on, due_on, created_by) VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$caseNumber, $title, $client, $practiceArea, $status, $priority, $openedOn, $dueOn, $current_user['uid']]);
                log_activity($pdo, (int)$current_user['uid'], 'case_created', 'Opened case ' . $caseNumber . ' — ' . $title, ['case_id' => (int)$pdo->lastInsertId()]);
                flash('success', 'New matter opened.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT case_number, title FROM legalops_cases WHERE id = ?');
        $stmt->execute([$id]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('DELETE FROM legalops_cases WHERE id = ?')->execute([$id]);
            log_activity($pdo, (int)$current_user['uid'], 'case_deleted', 'Removed case ' . $row['case_number'] . ' — ' . $row['title']);
            flash('success', 'Matter removed.');
        }
    }

    header('Location: ' . base_url('cases.php'));
    exit;
}

// ---- Fetch for listing ----------------------------------------------------
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM legalops_cases WHERE 1=1';
$params = [];
if (in_array($statusFilter, ['open', 'pending', 'closed'], true)) {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR client_name LIKE ? OR case_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Document counts per case, so the list shows at a glance which matters
// have files attached — same idea as a task count would, just for docs.
$docCounts = [];
if ($cases) {
    $ids = array_column($cases, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT case_id, COUNT(*) AS n FROM legalops_case_documents WHERE case_id IN ($placeholders) GROUP BY case_id");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $docCounts[(int)$row['case_id']] = (int)$row['n'];
    }
}

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Practice ledger</span>
    <h1>Cases</h1>
    <p class="sub"><?= count($cases) ?> matter<?= count($cases) === 1 ? '' : 's' ?> <?= $statusFilter !== 'all' ? 'with status “' . htmlspecialchars($statusFilter) . '”' : 'on file' ?>.</p>
  </div>
  <button class="btn btn-primary" type="button" id="case-toggle-btn"><?= icon('plus') ?> New matter</button>
</div>

<!-- Inline add/edit panel (no modal — sits in the page flow) -->
<div class="card inline-panel" id="case-panel">
  <form method="post">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="case-panel-title">New matter</h3>
      <span class="modal-close" id="case-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f-id" value="">

      <div class="input-row">
        <div class="field">
          <label>Matter number</label>
          <input class="input mono" type="text" name="case_number" id="f-case_number" placeholder="LO-2026-XXX" required>
        </div>
        <div class="field">
          <label>Practice area</label>
          <input class="input" type="text" name="practice_area" id="f-practice_area" placeholder="e.g. Corporate">
        </div>
      </div>

      <div class="field">
        <label>Matter title</label>
        <input class="input" type="text" name="title" id="f-title" placeholder="Short descriptive title" required>
      </div>

      <div class="field">
        <label>Client name</label>
        <input class="input" type="text" name="client_name" id="f-client_name" placeholder="Client or company name" required>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Status</label>
          <select class="input" name="status" id="f-status">
            <option value="open">Open</option>
            <option value="pending">Pending</option>
            <option value="closed">Closed</option>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="f-priority">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Opened on</label>
          <input class="input" type="date" name="opened_on" id="f-opened_on">
        </div>
        <div class="field">
          <label>Due on</label>
          <input class="input" type="date" name="due_on" id="f-due_on">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="case-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save matter</button>
      </div>
    </div>
  </form>
</div>

<form method="get" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search by title, client or matter no…" value="<?= htmlspecialchars($search) ?>">
  <a class="filter-chip <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">All</a>
  <a class="filter-chip <?= $statusFilter === 'open' ? 'active' : '' ?>" href="?status=open">Open</a>
  <a class="filter-chip <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
  <a class="filter-chip <?= $statusFilter === 'closed' ? 'active' : '' ?>" href="?status=closed">Closed</a>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($cases): ?>
  <table class="table">
    <thead>
      <tr><th>Matter</th><th>Client</th><th>Practice area</th><th>Status</th><th>Priority</th><th>Due</th><th>Docs</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($cases as $c): ?>
      <tr>
        <td>
          <a class="case-title" style="text-decoration:none;color:inherit" href="<?= base_url('case-view.php?id=' . (int)$c['id']) ?>"><?= htmlspecialchars($c['title']) ?></a>
          <div class="case-number"><?= htmlspecialchars($c['case_number']) ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars($c['client_name']) ?></td>
        <td class="case-client"><?= htmlspecialchars($c['practice_area'] ?: '—') ?></td>
        <td><span class="badge badge-<?= htmlspecialchars($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
        <td><span class="badge badge-<?= htmlspecialchars($c['priority']) ?>"><?= htmlspecialchars($c['priority']) ?></span></td>
        <td class="case-client"><?= $c['due_on'] ? date('d M Y', strtotime($c['due_on'])) : '—' ?></td>
        <td class="case-client">
          <a style="display:inline-flex;align-items:center;gap:5px;color:inherit;text-decoration:none" href="<?= base_url('case-view.php?id=' . (int)$c['id'] . '#documents') ?>">
            <?= icon('documents') ?> <?= (int)($docCounts[$c['id']] ?? 0) ?>
          </a>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= base_url('case-view.php?id=' . (int)$c['id']) ?>" title="View matter"><?= icon('briefcase') ?></a>
          <button class="icon-btn btn-sm case-edit-btn" style="display:inline-grid"
            type="button"
            data-case='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
          <form method="post" style="display:inline" onsubmit="return confirm('Remove this matter? This can\'t be undone.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" type="submit"><?= icon('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('briefcase') ?><p>No matters match that search.</p></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var panel = document.getElementById('case-panel');
  var form = panel.querySelector('form');
  var title = document.getElementById('case-panel-title');

  function openPanel(scroll) {
    panel.classList.add('open');
    if (scroll) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function closePanel() {
    panel.classList.remove('open');
  }
  function resetForm() {
    form.reset();
    document.getElementById('f-id').value = '';
    title.textContent = 'New matter';
  }

  document.getElementById('case-toggle-btn').addEventListener('click', function () {
    if (panel.classList.contains('open') && document.getElementById('f-id').value === '') {
      closePanel();
    } else {
      resetForm();
      openPanel(true);
      document.getElementById('f-case_number').focus();
    }
  });

  document.getElementById('case-cancel-btn').addEventListener('click', closePanel);
  document.getElementById('case-panel-close').addEventListener('click', closePanel);

  document.querySelectorAll('.case-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var c = JSON.parse(btn.getAttribute('data-case'));
      title.textContent = 'Edit matter';
      document.getElementById('f-id').value = c.id;
      document.getElementById('f-case_number').value = c.case_number;
      document.getElementById('f-title').value = c.title;
      document.getElementById('f-client_name').value = c.client_name;
      document.getElementById('f-practice_area').value = c.practice_area || '';
      document.getElementById('f-status').value = c.status;
      document.getElementById('f-priority').value = c.priority;
      document.getElementById('f-opened_on').value = c.opened_on || '';
      document.getElementById('f-due_on').value = c.due_on || '';
      openPanel(true);
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

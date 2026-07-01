<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/case_types.php';
$current_user = require_login($auth);

$page_title = 'Documents';
$active_nav = 'documents';
$breadcrumb = [
    ['label' => 'Documents'],
];

// ---- Fetch for listing ----------------------------------------------------
// This is a firm-wide library: every document attached to every matter,
// searchable across matter number/title/client and document type. It's
// intentionally read/triage-only here — uploading and deleting individual
// files happens on the matter itself (case-view.php), where the upload
// is in context. This page is for finding things across matters fast.
$docType = $_GET['type'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT d.*, c.case_number, c.title AS case_title, c.client_name
        FROM legalops_case_documents d
        JOIN legalops_cases c ON c.id = d.case_id
        WHERE 1=1";
$params = [];
if ($docType !== 'all' && in_array($docType, case_doc_types(), true)) {
    $sql .= ' AND d.doc_type = ?';
    $params[] = $docType;
}
if ($search !== '') {
    $sql .= ' AND (d.original_name LIKE ? OR c.title LIKE ? OR c.client_name LIKE ? OR c.case_number LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY d.uploaded_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query('SELECT COUNT(*) FROM legalops_case_documents');
$totalCount = (int)$stmt->fetchColumn();

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Document library</span>
    <h1>Documents</h1>
    <p class="sub"><?= count($documents) ?> of <?= $totalCount ?> document<?= $totalCount === 1 ? '' : 's' ?> across all matters<?= $docType !== 'all' ? ', filtered to “' . htmlspecialchars($docType) . '”' : '' ?>.</p>
  </div>
</div>

<form method="get" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search by file, matter, client or matter no…" value="<?= htmlspecialchars($search) ?>">
  <a class="filter-chip <?= $docType === 'all' ? 'active' : '' ?>" href="?type=all">All</a>
  <?php foreach (case_doc_types() as $dt): ?>
    <a class="filter-chip <?= $docType === $dt ? 'active' : '' ?>" href="?type=<?= urlencode($dt) ?>"><?= htmlspecialchars($dt) ?></a>
  <?php endforeach; ?>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($documents): ?>
  <table class="table">
    <thead>
      <tr><th>File</th><th>Type</th><th>Matter</th><th>Client</th><th>Size</th><th>Uploaded</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($documents as $d): ?>
      <tr>
        <td>
          <div class="case-title"><?= htmlspecialchars($d['original_name']) ?></div>
          <?php if ($d['notes']): ?><div class="case-number"><?= htmlspecialchars($d['notes']) ?></div><?php endif; ?>
        </td>
        <td class="case-client"><?= htmlspecialchars($d['doc_type']) ?></td>
        <td>
          <a class="case-title" style="font-weight:500;text-decoration:none;color:inherit" href="<?= base_url('case-view.php?id=' . (int)$d['case_id']) ?>"><?= htmlspecialchars($d['case_title']) ?></a>
          <div class="case-number"><?= htmlspecialchars($d['case_number']) ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars($d['client_name']) ?></td>
        <td class="case-client"><?= round($d['file_size'] / 1024) ?>KB</td>
        <td class="case-client"><?= time_ago($d['uploaded_at']) ?></td>
        <td style="text-align:right;white-space:nowrap">
          <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= base_url('download.php?doc=' . (int)$d['id'] . '&type=case') ?>" target="_blank" rel="noopener" title="Download"><?= icon('download') ?></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('documents') ?><p><?= $totalCount === 0 ? 'No documents uploaded yet — open a matter and upload one there.' : 'No documents match that search.' ?></p></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/client_types.php';
$current_user = require_login($auth);

$page_title = 'Clients';
$active_nav = 'clients';
$breadcrumb = [['label' => 'Clients']];
$types = client_types();

// ---- Handle new-client creation (editing core details happens on the
//      client detail page, not here) ---------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid() && ($_POST['action'] ?? '') === 'create') {
    $entityType = array_key_exists($_POST['entity_type'] ?? '', $types) ? $_POST['entity_type'] : 'individual';
    $displayName = trim($_POST['display_name'] ?? '');
    $pan = strtoupper(trim($_POST['pan'] ?? ''));
    $registration = trim($_POST['registration_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $addr1 = trim($_POST['address_line1'] ?? '');
    $addr2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');

    if ($displayName === '') {
        flash('error', 'Client / entity name is required.');
    } elseif ($types[$entityType]['registration_required'] && $registration === '') {
        flash('error', client_type_label($entityType) . ' clients need a ' . strtolower($types[$entityType]['registration_label']) . '.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO legalops_clients
             (entity_type, display_name, pan, registration_number, email, phone, address_line1, address_line2, city, state, pincode, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$entityType, $displayName, $pan ?: null, $registration ?: null, $email ?: null, $phone ?: null, $addr1 ?: null, $addr2 ?: null, $city ?: null, $state ?: null, $pincode ?: null, $current_user['uid']]);
        $newId = (int)$pdo->lastInsertId();

        log_activity($pdo, (int)$current_user['uid'], 'client_onboarded', 'Started onboarding ' . client_type_label($entityType) . ' client — ' . $displayName);
        flash('success', 'Client created — continue onboarding below.');
        header('Location: ' . base_url('client-view.php?id=' . $newId));
        exit;
    }
}

// ---- Listing ---------------------------------------------------------------
$entityFilter = $_GET['type'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT * FROM legalops_clients WHERE 1=1';
$params = [];
if (array_key_exists($entityFilter, $types)) {
    $sql .= ' AND entity_type = ?';
    $params[] = $entityFilter;
}
if (in_array($statusFilter, CLIENT_ONBOARDING_STAGES, true)) {
    $sql .= ' AND onboarding_status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $sql .= ' AND (display_name LIKE ? OR pan LIKE ? OR registration_number LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$sql .= ' ORDER BY created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Client register</span>
    <h1>Clients</h1>
    <p class="sub"><?= count($clients) ?> client<?= count($clients) === 1 ? '' : 's' ?> on file.</p>
  </div>
  <button class="btn btn-primary" type="button" id="client-toggle-btn"><?= icon('plus') ?> New client</button>
</div>

<!-- Inline onboarding panel -->
<div class="card inline-panel" id="client-panel">
  <form method="post">
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Onboard a new client</h3>
      <span class="modal-close" id="client-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="field">
        <label>Entity type</label>
        <select class="input" name="entity_type" id="f-entity-type">
          <?php foreach ($types as $key => $meta): ?>
            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($meta['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="entity-hint"></div>
      </div>

      <div class="field">
        <label id="name-label">Client / entity name</label>
        <input class="input" type="text" name="display_name" id="f-display-name" placeholder="Full name or registered entity name" required>
      </div>

      <div class="input-row">
        <div class="field">
          <label>PAN</label>
          <input class="input mono" type="text" name="pan" maxlength="10" placeholder="ABCDE1234F" style="text-transform:uppercase">
        </div>
        <div class="field" id="registration-field">
          <label id="registration-label">Registration no.</label>
          <input class="input mono" type="text" name="registration_number" id="f-registration">
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Email</label>
          <input class="input" type="email" name="email" placeholder="name@example.com">
        </div>
        <div class="field">
          <label>Phone</label>
          <input class="input" type="text" name="phone" placeholder="+91 …">
        </div>
      </div>

      <div class="field">
        <label>Address line 1</label>
        <input class="input" type="text" name="address_line1" placeholder="Door no., street">
      </div>
      <div class="field">
        <label>Address line 2</label>
        <input class="input" type="text" name="address_line2" placeholder="Area / landmark (optional)">
      </div>
      <div class="input-row">
        <div class="field"><label>City</label><input class="input" type="text" name="city"></div>
        <div class="field"><label>State</label><input class="input" type="text" name="state"></div>
        <div class="field"><label>PIN code</label><input class="input" type="text" name="pincode"></div>
      </div>

      <div class="alert alert-info" style="margin-top:4px">
        After saving, you'll land on the client's page to add <?= /* dynamic via JS below, default text */ '' ?><span id="leadership-hint">leadership / KYC details</span>, secondary contacts, and documents.
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="client-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Create client &amp; continue onboarding</button>
      </div>
    </div>
  </form>
</div>

<form method="get" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search by name, PAN, registration no., email…" value="<?= htmlspecialchars($search) ?>">
  <select class="input" name="type" onchange="this.form.submit()">
    <option value="all">All entity types</option>
    <?php foreach ($types as $key => $meta): ?>
      <option value="<?= htmlspecialchars($key) ?>" <?= $entityFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <select class="input" name="status" onchange="this.form.submit()">
    <option value="all">All stages</option>
    <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
    <option value="kyc_pending" <?= $statusFilter === 'kyc_pending' ? 'selected' : '' ?>>KYC pending</option>
    <option value="kyc_verified" <?= $statusFilter === 'kyc_verified' ? 'selected' : '' ?>>KYC verified</option>
    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($clients): ?>
  <table class="table">
    <thead>
      <tr><th>Client</th><th>Type</th><th>PAN / Registration</th><th>Contact</th><th>Onboarding</th><th>KYC</th></tr>
    </thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr style="cursor:pointer" onclick="window.location='<?= base_url('client-view.php?id=' . (int)$c['id']) ?>'">
        <td>
          <div class="case-title"><?= htmlspecialchars($c['display_name']) ?></div>
          <div class="case-client"><?= htmlspecialchars($c['city'] ?: '—') ?><?= $c['state'] ? ', ' . htmlspecialchars($c['state']) : '' ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars(client_type_label($c['entity_type'])) ?></td>
        <td>
          <div class="mono case-client"><?= htmlspecialchars($c['pan'] ?: '—') ?></div>
          <div class="mono case-client" style="opacity:.75"><?= htmlspecialchars($c['registration_number'] ?: '') ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars($c['email'] ?: $c['phone'] ?: '—') ?></td>
        <td><span class="badge badge-onboard-<?= htmlspecialchars($c['onboarding_status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $c['onboarding_status'])) ?></span></td>
        <td><span class="badge badge-kyc-<?= htmlspecialchars($c['kyc_status']) ?>"><?= htmlspecialchars($c['kyc_status']) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('clients') ?><p>No clients match that search.</p></div>
  <?php endif; ?>
</div>

<script>
var CLIENT_TYPES = <?= json_encode($types) ?>;

(function () {
  var panel = document.getElementById('client-panel');
  document.getElementById('client-toggle-btn').addEventListener('click', function () {
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      updateEntityFields();
    }
  });
  document.getElementById('client-cancel-btn').addEventListener('click', function () { panel.classList.remove('open'); });
  document.getElementById('client-panel-close').addEventListener('click', function () { panel.classList.remove('open'); });

  function updateEntityFields() {
    var type = document.getElementById('f-entity-type').value;
    var meta = CLIENT_TYPES[type];
    var regField = document.getElementById('registration-field');
    var regInput = document.getElementById('f-registration');
    var regLabel = document.getElementById('registration-label');
    var nameLabel = document.getElementById('name-label');
    var hint = document.getElementById('entity-hint');
    var leadershipHint = document.getElementById('leadership-hint');

    if (meta.registration_label) {
      regField.style.display = '';
      regLabel.textContent = meta.registration_label + (meta.registration_required ? ' *' : ' (optional)');
      regInput.required = !!meta.registration_required;
    } else {
      regField.style.display = 'none';
      regInput.required = false;
      regInput.value = '';
    }

    nameLabel.textContent = (type === 'individual') ? 'Full name' : 'Entity / registered name';
    hint.textContent = meta.leadership_singular
      ? 'Leadership for this type: ' + meta.leadership_label + ' (one person).'
      : 'Leadership for this type: ' + meta.leadership_label + ' (you can add several).';
    leadershipHint.textContent = meta.leadership_label.toLowerCase() + ' KYC';
  }

  document.getElementById('f-entity-type').addEventListener('change', updateEntityFields);
  updateEntityFields();
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>

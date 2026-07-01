<?php
require_once __DIR__ . '/config/bootstrap.php';
$current_user = require_login($auth);

$page_title = 'Billing entities';
$active_nav = 'billing';
$breadcrumb = [
    ['label' => 'Billing', 'href' => 'billing.php'],
    ['label' => 'Billing entities'],
];

$countries = [
    'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'BH' => 'Bahrain',
    'OM' => 'Oman', 'KW' => 'Kuwait', 'QA' => 'Qatar',
];
$entityTypes = [
    'IN_GST'  => 'India — GST registered',
    'GCC_VAT' => 'GCC — VAT registered',
    'NO_VAT'  => 'No VAT/GST registration',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $country = trim($_POST['country'] ?? '');

        if ($name === '' || $country === '') {
            flash('error', 'Name and country are required.');
        } else {
            $fields = [
                $name, $country, $_POST['entity_type'] ?? 'NO_VAT',
                trim($_POST['tax_reg_no'] ?? '') ?: null,
                trim($_POST['state_or_emirate'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null,
                trim($_POST['default_currency'] ?? '') ?: 'INR',
                trim($_POST['invoice_prefix'] ?? '') ?: 'INV',
                trim($_POST['bank_details'] ?? '') ?: null,
            ];
            if ($id > 0) {
                $pdo->prepare(
                    'UPDATE legalops_billing_entities SET name=?, country=?, entity_type=?, tax_reg_no=?, '
                    . 'state_or_emirate=?, address=?, default_currency=?, invoice_prefix=?, bank_details=? WHERE id=?'
                )->execute([...$fields, $id]);
                flash('success', 'Billing entity updated.');
            } else {
                $pdo->prepare(
                    'INSERT INTO legalops_billing_entities '
                    . '(name, country, entity_type, tax_reg_no, state_or_emirate, address, default_currency, invoice_prefix, bank_details) '
                    . 'VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute($fields);
                flash('success', 'Billing entity added.');
            }
            log_activity($pdo, (int)$current_user['uid'], 'billing_entity_saved', 'Saved billing entity ' . $name);
        }
    } elseif ($action === 'deactivate') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE legalops_billing_entities SET is_active = 0 WHERE id = ?')->execute([$id]);
        flash('success', 'Billing entity deactivated — past invoices are unaffected.');
    }

    header('Location: ' . base_url('billing_entities.php'));
    exit;
}

$entities = $pdo->query('SELECT * FROM legalops_billing_entities ORDER BY is_active DESC, name')->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Practice ledger</span>
    <h1>Billing entities</h1>
    <p class="sub">The legal entities you issue invoices from — one per country/registration.</p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= base_url('billing.php') ?>">← Back to billing</a>
    <button class="btn btn-primary" type="button" id="entity-toggle-btn"><?= icon('plus') ?> New entity</button>
  </div>
</div>

<div class="card inline-panel" id="entity-panel">
  <form method="post" id="entity-form">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="entity-panel-title">New billing entity</h3>
      <span class="modal-close" id="entity-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="e-id" value="">

      <div class="input-row">
        <div class="field">
          <label>Entity name</label>
          <input class="input" type="text" name="name" id="e-name" placeholder="e.g. LegalOps India Pvt Ltd" required>
        </div>
        <div class="field">
          <label>Country</label>
          <select class="input" name="country" id="e-country" required>
            <?php foreach ($countries as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Registration type</label>
          <select class="input" name="entity_type" id="e-entity_type">
            <?php foreach ($entityTypes as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>GSTIN / TRN</label>
          <input class="input mono" type="text" name="tax_reg_no" id="e-tax_reg_no" placeholder="Tax registration number">
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>State / Emirate</label>
          <input class="input" type="text" name="state_or_emirate" id="e-state_or_emirate" placeholder="e.g. Tamil Nadu, or Dubai">
          <div class="small muted" style="margin-top:4px">For India, this decides CGST+SGST vs IGST against the client's place of supply.</div>
        </div>
        <div class="field">
          <label>Default currency</label>
          <input class="input mono" type="text" name="default_currency" id="e-default_currency" maxlength="3" style="text-transform:uppercase" placeholder="INR">
        </div>
      </div>

      <div class="field">
        <label>Address</label>
        <textarea class="input" name="address" id="e-address" rows="2"></textarea>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Invoice number prefix</label>
          <input class="input mono" type="text" name="invoice_prefix" id="e-invoice_prefix" placeholder="e.g. LO-IN or LO-AE">
        </div>
        <div class="field">
          <label>Bank details (printed on invoices)</label>
          <input class="input" type="text" name="bank_details" id="e-bank_details" placeholder="Bank, A/C, IFSC/IBAN/SWIFT">
        </div>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-ghost" id="entity-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </div>
  </form>
</div>

<div class="card card-pad">
  <?php if ($entities): ?>
  <table class="table">
    <thead><tr><th>Name</th><th>Country</th><th>Type</th><th>GSTIN/TRN</th><th>Prefix</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($entities as $e): ?>
        <tr>
          <td class="case-title"><?= htmlspecialchars($e['name']) ?></td>
          <td><?= htmlspecialchars($e['country']) ?></td>
          <td><?= htmlspecialchars($entityTypes[$e['entity_type']] ?? $e['entity_type']) ?></td>
          <td class="mono"><?= htmlspecialchars($e['tax_reg_no'] ?? '—') ?></td>
          <td class="mono"><?= htmlspecialchars($e['invoice_prefix']) ?></td>
          <td><span class="badge badge-<?= $e['is_active'] ? 'open' : 'closed' ?>"><?= $e['is_active'] ? 'Active' : 'Inactive' ?></span></td>
          <td style="text-align:right;white-space:nowrap">
            <button class="icon-btn btn-sm entity-edit-btn" style="display:inline-grid" type="button"
              data-entity='<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
            <?php if ($e['is_active']): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Deactivate this entity? Past invoices stay exactly as they are.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
                <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" type="submit"><?= icon('trash') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('building') ?><p>No billing entities yet.</p></div>
  <?php endif; ?>
</div>

<script>
(function () {
  var panel = document.getElementById('entity-panel');
  var form = document.getElementById('entity-form');
  function openPanel() { panel.classList.add('open'); panel.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  function closePanel() { panel.classList.remove('open'); }

  document.getElementById('entity-toggle-btn').addEventListener('click', function () {
    form.reset();
    document.getElementById('e-id').value = '';
    document.getElementById('entity-panel-title').textContent = 'New billing entity';
    openPanel();
  });
  document.getElementById('entity-cancel-btn').addEventListener('click', closePanel);
  document.getElementById('entity-panel-close').addEventListener('click', closePanel);

  document.querySelectorAll('.entity-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var e = JSON.parse(btn.getAttribute('data-entity'));
      document.getElementById('entity-panel-title').textContent = 'Edit billing entity';
      document.getElementById('e-id').value = e.id;
      document.getElementById('e-name').value = e.name;
      document.getElementById('e-country').value = e.country;
      document.getElementById('e-entity_type').value = e.entity_type;
      document.getElementById('e-tax_reg_no').value = e.tax_reg_no || '';
      document.getElementById('e-state_or_emirate').value = e.state_or_emirate || '';
      document.getElementById('e-default_currency').value = e.default_currency;
      document.getElementById('e-address').value = e.address || '';
      document.getElementById('e-invoice_prefix').value = e.invoice_prefix;
      document.getElementById('e-bank_details').value = e.bank_details || '';
      openPanel();
    });
  });
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>
